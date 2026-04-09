#!/usr/bin/env python3
"""
Daily Merge Request Report — posts a categorized summary to Slack.

Queries the GitLab API for all open merge requests, categorizes them by
status (approved, awaiting review, CI broken, needs rebase, other blockers),
and sends a structured Slack message via incoming webhook.

Required environment variables:
  CI_PROJECT_PATH      - GitLab project path e.g. "group/project" (set automatically by CI)
  CI_SERVER_URL        - GitLab server URL (default: https://gitlab.com)
  GITLAB_TOKEN         - GitLab API token with api scope
  SLACK_WEBHOOK_URL    - Slack incoming webhook URL for the target channel
  SLACK_USER_MAPPING   - JSON string mapping GitLab usernames to Slack user IDs

Optional:
  --dry-run            - Print Slack payload to stdout instead of posting
"""

import argparse
import json
import logging
import os
import sys
from datetime import datetime, timezone
from typing import Any, Optional

import requests

logging.basicConfig(
    level=logging.INFO,
    format="%(levelname)s: %(message)s",
)
logger = logging.getLogger(__name__)

# Maximum number of blocks per Slack message (API limit is 50)
MAX_SLACK_BLOCKS = 50
# Maximum characters per mrkdwn text block
MAX_MRKDWN_LENGTH = 3000

STALE_DAYS = 3
OLD_DRAFT_DAYS = 5

CATEGORIES: list[dict[str, str]] = [
    {
        "key": "approved_ready",
        "emoji": ":white_check_mark:",
        "title": "Approved & Ready to Merge",
        "action": "à merger",
    },
    {
        "key": "awaiting_review",
        "emoji": ":eyes:",
        "title": "Awaiting Review",
        "action": "à reviewer",
    },
    {
        "key": "needs_reviewer",
        "emoji": ":bust_in_silhouette:",
        "title": "Needs Reviewer",
        "action": "reviewer à assigner",
    },
    {
        "key": "ci_broken",
        "emoji": ":x:",
        "title": "CI Broken",
        "action": "CI cassée — à fixer",
    },
    {
        "key": "needs_rebase",
        "emoji": ":warning:",
        "title": "Needs Rebase",
        "action": "rebase nécessaire",
    },
    {
        "key": "other_blockers",
        "emoji": ":hourglass_flowing_sand:",
        "title": "Other Blockers",
        "action": "",
    },
]


def get_env(name: str, default: Optional[str] = None) -> str:
    """Retrieve a required environment variable or exit with an error."""
    value = os.environ.get(name, default)
    if value is None:
        logger.error("Missing required environment variable: %s", name)
        sys.exit(1)
    return value


def load_user_mapping() -> dict[str, str]:
    """Load GitLab username → Slack user ID mapping from SLACK_USER_MAPPING env var."""
    raw = os.environ.get("SLACK_USER_MAPPING", "")
    if not raw:
        logger.warning("SLACK_USER_MAPPING is empty — mentions will use @username")
        return {}

    try:
        data = json.loads(raw)
    except json.JSONDecodeError as exc:
        logger.warning("Failed to parse SLACK_USER_MAPPING: %s — mentions will use @username", exc)
        return {}

    return {k: v for k, v in data.items() if isinstance(v, str)}


BOT_USERS: set[str] = {"ChapsMindBot"}

_warned_users: set[str] = set()


def slack_mention(gitlab_username: str, mapping: dict[str, str]) -> str:
    """Return a Slack mention for a GitLab user, or @username as fallback."""
    if gitlab_username in BOT_USERS:
        return f"{gitlab_username} (bot)"
    slack_id = mapping.get(gitlab_username)
    if slack_id:
        return f"<@{slack_id}>"
    if gitlab_username not in _warned_users:
        _warned_users.add(gitlab_username)
        logger.warning("No Slack mapping for GitLab user '%s'", gitlab_username)
    return f"@{gitlab_username}"


def business_days_since(iso_timestamp: str) -> int:
    """Count weekdays (Mon–Fri) between a timestamp and now (UTC)."""
    updated = datetime.fromisoformat(iso_timestamp.replace("Z", "+00:00"))
    now = datetime.now(timezone.utc)
    count = 0
    current = updated.date()
    today = now.date()
    while current < today:
        current = current.fromordinal(current.toordinal() + 1)
        if current.weekday() < 5:  # Mon=0 … Fri=4
            count += 1
    return count


# ─── GitLab GraphQL API ─────────────────────────────────

_OPEN_MRS_QUERY = """
query($projectPath: ID!, $after: String) {
  project(fullPath: $projectPath) {
    mergeRequests(state: opened, first: 100, after: $after) {
      pageInfo {
        hasNextPage
        endCursor
      }
      nodes {
        iid
        title
        webUrl
        draft
        author { username }
        reviewers { nodes { username } }
        headPipeline { status }
        mergeStatusEnum
        conflicts

        updatedAt
        userNotesCount
        approvedBy { nodes { username } }
        discussions { nodes { resolvable resolved } }
      }
    }
  }
}
"""


def _all_discussions_resolved(node: dict[str, Any]) -> bool:
    """Check if all resolvable discussions are resolved."""
    discussions = node.get("discussions", {}).get("nodes", [])
    return all(
        d.get("resolved", True)
        for d in discussions
        if d.get("resolvable", False)
    )


def fetch_open_mrs_graphql(
    server_url: str,
    project_path: str,
    token: str,
) -> tuple[list[dict[str, Any]], dict[int, list[str]]]:
    """Fetch all open MRs with approvals in a single GraphQL query (paginated)."""
    headers = {"PRIVATE-TOKEN": token, "Content-Type": "application/json"}
    all_mrs: list[dict[str, Any]] = []
    approvals_map: dict[int, list[str]] = {}
    after: Optional[str] = None

    while True:
        variables: dict[str, Any] = {"projectPath": project_path}
        if after:
            variables["after"] = after

        response = requests.post(
            f"{server_url}/api/graphql",
            headers=headers,
            json={"query": _OPEN_MRS_QUERY, "variables": variables},
            timeout=30,
        )
        response.raise_for_status()
        result = response.json()

        if "errors" in result:
            logger.error("GraphQL errors: %s", result["errors"])
            sys.exit(1)

        mr_data = result["data"]["project"]["mergeRequests"]

        for node in mr_data["nodes"]:
            iid = int(node["iid"])
            pipeline = node.get("headPipeline")
            merge_status_enum = (node.get("mergeStatusEnum") or "").lower()

            mr: dict[str, Any] = {
                "iid": iid,
                "title": node["title"],
                "web_url": node["webUrl"],
                "draft": node["draft"],
                "author": node["author"],
                "reviewers": node.get("reviewers", {}).get("nodes", []),
                "pipeline": {"status": pipeline["status"].lower()} if pipeline else None,
                "has_conflicts": node.get("conflicts", False),
                "merge_status": merge_status_enum,

                "updated_at": node["updatedAt"],
                "user_notes_count": node.get("userNotesCount", 0),
                "blocking_discussions_resolved": _all_discussions_resolved(node),
            }
            all_mrs.append(mr)

            approved_by = node.get("approvedBy", {}).get("nodes", [])
            approvals_map[iid] = [a["username"] for a in approved_by]

        page_info = mr_data["pageInfo"]
        if not page_info["hasNextPage"]:
            break
        after = page_info["endCursor"]

    logger.info("Fetched %d open merge request(s) via GraphQL", len(all_mrs))
    return all_mrs, approvals_map


# ─── Categorization ──────────────────────────────────────


def categorize_mrs(
    mrs: list[dict[str, Any]],
    approvals_map: dict[int, list[str]],
) -> dict[str, list[dict[str, Any]]]:
    """
    Categorize MRs into buckets. Each MR goes into the first matching category.

    approvals_map: {mr_iid: [list of usernames who approved]}
    """
    categorized: dict[str, list[dict[str, Any]]] = {cat["key"]: [] for cat in CATEGORIES}

    for mr in mrs:
        iid = mr["iid"]
        approved_by = approvals_map.get(iid, [])
        pipeline = mr.get("pipeline")
        pipeline_status = pipeline.get("status") if pipeline else None
        has_conflicts = mr.get("has_conflicts", False)
        merge_status = mr.get("merge_status", "")
        is_draft = mr.get("draft", False)
        reviewers = mr.get("reviewers", [])
        updated_at = mr.get("updated_at", "")
        discussions_resolved = mr.get("blocking_discussions_resolved", True)
        days_since_update = business_days_since(updated_at) if updated_at else 0

        # Category 1: Approved & ready to merge
        if (
            len(approved_by) >= 1
            and pipeline_status == "success"
            and not has_conflicts
            and merge_status not in ("cannot_be_merged",)
            and not is_draft
        ):
            categorized["approved_ready"].append(mr)
            continue

        # Category 2: Awaiting review (reviewer assigned, no review activity yet)
        notes_count = mr.get("user_notes_count", 0)
        if reviewers and len(approved_by) == 0 and notes_count == 0 and not is_draft:
            categorized["awaiting_review"].append(mr)
            continue

        # Category 3: Needs reviewer (no reviewer assigned, not draft)
        if not reviewers and len(approved_by) == 0 and not is_draft:
            categorized["needs_reviewer"].append(mr)
            continue

        # Category 4: CI broken
        if pipeline_status == "failed":
            categorized["ci_broken"].append(mr)
            continue

        # Category 5: Needs rebase
        if has_conflicts or merge_status == "cannot_be_merged":
            categorized["needs_rebase"].append(mr)
            continue

        # Category 6: Other blockers
        blocker_reason = _get_blocker_reason(
            is_draft, days_since_update, discussions_resolved,
        )
        if blocker_reason:
            mr["_blocker_reason"] = blocker_reason
            categorized["other_blockers"].append(mr)
            continue

        # Draft MRs still in progress (recent, no blockers)
        if is_draft:
            mr["_blocker_reason"] = "en cours de développement"
            categorized["other_blockers"].append(mr)
            continue

        # MRs with comments but no approval — likely needs rework
        mr["_blocker_reason"] = "retours à prendre en compte"
        categorized["other_blockers"].append(mr)

    return categorized


def _get_blocker_reason(
    is_draft: bool,
    days_since_update: int,
    discussions_resolved: bool,
) -> str:
    """Return a human-readable blocker reason, or empty string if none."""
    reasons: list[str] = []
    if is_draft and days_since_update >= OLD_DRAFT_DAYS:
        reasons.append(f"draft depuis {days_since_update}j ouvrés")
    if not is_draft and days_since_update >= STALE_DAYS:
        reasons.append(f"aucune activité depuis {days_since_update}j ouvrés")
    if not discussions_resolved:
        reasons.append("threads non résolus")
    return ", ".join(reasons)


# ─── Slack message builder ───────────────────────────────


def _format_mr_line(
    mr: dict[str, Any],
    mapping: dict[str, str],
    action: str,
    ping_target: str = "author",
) -> str:
    """Format a single MR as a Slack mrkdwn line."""
    iid = mr["iid"]
    title = mr["title"]
    url = mr["web_url"]
    author = mr.get("author", {}).get("username", "unknown")
    reviewers = mr.get("reviewers", [])

    # Determine who to ping
    if ping_target == "reviewers" and reviewers:
        pings = ", ".join(slack_mention(r["username"], mapping) for r in reviewers)
    else:
        pings = slack_mention(author, mapping)

    # Use blocker reason for "other_blockers" category
    display_action = mr.get("_blocker_reason", action)

    reviewer_names = ", ".join(r["username"] for r in reviewers) if reviewers else "—"

    return (
        f"• <{url}|!{iid} {title}>\n"
        f"   _auteur:_ {author} · _reviewer(s):_ {reviewer_names} · {pings} *{display_action}*"
    )


def build_slack_blocks(
    categorized: dict[str, list[dict[str, Any]]],
    mapping: dict[str, str],
) -> list[dict[str, Any]]:
    """Build Slack Block Kit blocks from categorized MRs."""
    total = sum(len(mrs) for mrs in categorized.values())

    if total == 0:
        return _build_no_mr_blocks()

    today = datetime.now(timezone.utc).strftime("%d/%m/%Y")
    blocks: list[dict[str, Any]] = [
        {
            "type": "header",
            "text": {"type": "plain_text", "text": f"Daily MR Report — {today}", "emoji": True},
        },
        {
            "type": "section",
            "text": {
                "type": "mrkdwn",
                "text": f"*{total}* merge request{'s' if total > 1 else ''} ouverte{'s' if total > 1 else ''}",
            },
        },
    ]

    for cat in CATEGORIES:
        mrs = categorized.get(cat["key"], [])
        if not mrs:
            continue

        blocks.append({"type": "divider"})
        blocks.append({
            "type": "section",
            "text": {
                "type": "mrkdwn",
                "text": f"{cat['emoji']} *{cat['title']} ({len(mrs)})*",
            },
        })

        # Determine ping target for this category
        ping_target = "reviewers" if cat["key"] == "awaiting_review" else "author"

        # Build MR lines, splitting if they exceed Slack's text limit
        lines: list[str] = []
        for mr in mrs:
            lines.append(_format_mr_line(mr, mapping, cat["action"], ping_target))

        current_text = ""
        for line in lines:
            candidate = f"{current_text}\n{line}" if current_text else line
            if len(candidate) > MAX_MRKDWN_LENGTH:
                blocks.append({
                    "type": "section",
                    "text": {"type": "mrkdwn", "text": current_text},
                })
                current_text = line
            else:
                current_text = candidate

        if current_text:
            blocks.append({
                "type": "section",
                "text": {"type": "mrkdwn", "text": current_text},
            })

    return blocks


def _build_no_mr_blocks() -> list[dict[str, Any]]:
    """Build a short celebratory message when no MRs are open."""
    today = datetime.now(timezone.utc).strftime("%d/%m/%Y")
    return [
        {
            "type": "header",
            "text": {"type": "plain_text", "text": f"Daily MR Report — {today}", "emoji": True},
        },
        {
            "type": "section",
            "text": {
                "type": "mrkdwn",
                "text": ":tada: Aucune merge request ouverte — the board is clean!",
            },
        },
    ]


# ─── Slack delivery ──────────────────────────────────────


def post_to_slack(webhook_url: str, blocks: list[dict[str, Any]]) -> None:
    """Post blocks to Slack via incoming webhook, splitting if needed."""
    # Split into chunks of MAX_SLACK_BLOCKS if necessary
    for i in range(0, len(blocks), MAX_SLACK_BLOCKS):
        chunk = blocks[i : i + MAX_SLACK_BLOCKS]
        payload = {"blocks": chunk}
        response = requests.post(webhook_url, json=payload, timeout=30)
        response.raise_for_status()

    logger.info("Slack message posted successfully (%d block(s))", len(blocks))


# ─── Main ────────────────────────────────────────────────


def main() -> None:
    parser = argparse.ArgumentParser(description="Daily MR Report to Slack")
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Print Slack payload to stdout instead of posting",
    )
    args = parser.parse_args()

    server_url = get_env("CI_SERVER_URL", "https://gitlab.com").rstrip("/")
    project_path = get_env("CI_PROJECT_PATH")
    token = get_env("GITLAB_TOKEN")

    if not args.dry_run:
        webhook_url = get_env("SLACK_WEBHOOK_URL")
    else:
        webhook_url = ""

    # Load user mapping
    mapping = load_user_mapping()
    logger.info("Loaded %d user mapping(s)", len(mapping))

    # Fetch all open MRs with approvals in a single GraphQL query
    mrs, approvals_map = fetch_open_mrs_graphql(server_url, project_path, token)

    # Categorize
    categorized = categorize_mrs(mrs, approvals_map)
    for cat in CATEGORIES:
        count = len(categorized.get(cat["key"], []))
        if count:
            logger.info("  %s: %d MR(s)", cat["title"], count)

    # Build Slack message
    blocks = build_slack_blocks(categorized, mapping)

    if args.dry_run:
        print(json.dumps({"blocks": blocks}, indent=2, ensure_ascii=False))
        logger.info("Dry run — message not sent")
        return

    # Post to Slack
    post_to_slack(webhook_url, blocks)


if __name__ == "__main__":
    main()
