#!/usr/bin/env python3
"""
Assign merge request reviewers, assignee, and labels based on CODEOWNERS file.

This script reproduces GitLab Premium CODEOWNERS behavior on GitLab Free.
It reads the .gitlab/CODEOWNERS file, matches modified files from the MR
against ownership patterns, resolves group members via the GitLab API,
and assigns them as reviewers on the merge request.

Additionally, it:
- Auto-assigns the MR to its author if no assignee is set
- Auto-labels the MR based on which directories are modified

Required environment variables:
  CI_PROJECT_ID        - GitLab project ID (set automatically by CI)
  CI_MERGE_REQUEST_IID - MR internal ID (set automatically by CI)
  GITLAB_TOKEN         - GitLab API token with api scope
  CI_SERVER_URL        - GitLab server URL (default: https://gitlab.com)
"""

import fnmatch
import logging
import os
import random
import sys
from pathlib import Path
from typing import Optional

import requests

logging.basicConfig(
    level=logging.INFO,
    format="%(levelname)s: %(message)s",
)
logger = logging.getLogger(__name__)

CODEOWNERS_PATH = ".gitlab/CODEOWNERS"

# Mapping from path prefixes to GitLab labels.
# Each changed file is matched against these prefixes (first match wins per file).
PATH_LABELS: list[tuple[str, str]] = [
    ("apps/front/", "frontend"),
    ("apps/screen/", "screen"),
    ("apps/global-service/", "global-service"),
    ("infra/", "infra"),
    ("docs/", "docs"),
    ("scripts/", "tooling"),
    (".gitlab/", "ci"),
    ("alembic/", "database"),
]


def get_env(name: str, default: Optional[str] = None) -> str:
    """Retrieve a required environment variable or exit with an error."""
    value = os.environ.get(name, default)
    if value is None:
        logger.error("Missing required environment variable: %s", name)
        sys.exit(1)
    return value


def parse_codeowners(repo_root: str) -> list[tuple[str, list[str]]]:
    """
    Parse the CODEOWNERS file and return a list of (pattern, owners) tuples.

    Blank lines and comments (lines starting with #) are skipped.
    Each valid line has the format: <path-pattern> @owner1 @owner2 ...
    """
    codeowners_file = Path(repo_root) / CODEOWNERS_PATH
    if not codeowners_file.exists():
        logger.error("CODEOWNERS file not found at %s", codeowners_file)
        sys.exit(1)

    rules: list[tuple[str, list[str]]] = []
    for line in codeowners_file.read_text(encoding="utf-8").splitlines():
        stripped = line.strip()
        if not stripped or stripped.startswith("#"):
            continue
        parts = stripped.split()
        if len(parts) >= 2:
            pattern = parts[0]
            owners = parts[1:]
            rules.append((pattern, owners))
    return rules


def get_mr_changed_files(
    server_url: str,
    project_id: str,
    mr_iid: str,
    token: str,
) -> list[str]:
    """
    Fetch the list of changed file paths from a GitLab merge request.

    Uses GET /projects/:id/merge_requests/:iid/changes
    """
    url = f"{server_url}/api/v4/projects/{project_id}/merge_requests/{mr_iid}/changes"
    headers = {"PRIVATE-TOKEN": token}
    response = requests.get(url, headers=headers, timeout=30)
    response.raise_for_status()
    data = response.json()

    changed_files: list[str] = []
    for change in data.get("changes", []):
        # Use new_path for renamed/moved files, fall back to old_path
        file_path = change.get("new_path") or change.get("old_path")
        if file_path:
            changed_files.append(file_path)

    return changed_files


def match_owners(
    changed_files: list[str],
    rules: list[tuple[str, list[str]]],
) -> set[str]:
    """
    Match changed files against CODEOWNERS rules and return the set of owners.

    A CODEOWNERS pattern like `apps/front/` matches any file whose path
    starts with that prefix. Glob patterns (e.g. `*.py`) use fnmatch.
    """
    matched_owners: set[str] = set()
    for file_path in changed_files:
        for pattern, owners in rules:
            # Directory-style pattern: match if the file is under that directory
            if pattern.endswith("/") and file_path.startswith(pattern):
                matched_owners.update(owners)
            # Glob-style pattern
            elif fnmatch.fnmatch(file_path, pattern):
                matched_owners.update(owners)
    return matched_owners


def resolve_user(
    server_url: str,
    user_ref: str,
    token: str,
) -> Optional[int]:
    """
    Resolve a single @username from CODEOWNERS to a GitLab user ID.
    """
    username = user_ref.lstrip("@")
    url = f"{server_url}/api/v4/users"
    headers = {"PRIVATE-TOKEN": token}
    params = {"username": username}
    response = requests.get(url, headers=headers, params=params, timeout=30)
    response.raise_for_status()
    users = response.json()

    if users:
        return users[0]["id"]
    logger.warning("User '%s' not found on GitLab", username)
    return None


def match_labels(changed_files: list[str]) -> set[str]:
    """
    Match changed files against PATH_LABELS and return the set of labels to apply.

    Each file is tested against prefixes in order; a file can match multiple prefixes.
    """
    labels: set[str] = set()
    for file_path in changed_files:
        for prefix, label in PATH_LABELS:
            if file_path.startswith(prefix):
                labels.add(label)
    return labels


def ensure_labels_exist(
    server_url: str,
    project_id: str,
    token: str,
    label_names: set[str],
) -> None:
    """Create project labels if they don't already exist."""
    headers = {"PRIVATE-TOKEN": token}

    # Fetch existing labels (paginated, but we only need names)
    existing: set[str] = set()
    page = 1
    while True:
        url = f"{server_url}/api/v4/projects/{project_id}/labels"
        response = requests.get(
            url, headers=headers, params={"per_page": 100, "page": page}, timeout=30
        )
        response.raise_for_status()
        data = response.json()
        if not data:
            break
        existing.update(label["name"] for label in data)
        page += 1

    # Label colors for auto-created labels
    label_colors: dict[str, str] = {
        "frontend": "#428BCA",
        "screen": "#44AD8E",
        "global-service": "#A295D6",
        "infra": "#F0AD4E",
        "docs": "#5CB85C",
        "tooling": "#7F8C8D",
        "ci": "#E4D354",
        "database": "#D10069",
    }

    for name in label_names - existing:
        color = label_colors.get(name, "#6699CC")
        url = f"{server_url}/api/v4/projects/{project_id}/labels"
        response = requests.post(
            url,
            headers=headers,
            json={"name": name, "color": color},
            timeout=30,
        )
        if response.status_code == 409:
            # Label was created between our check and now
            continue
        response.raise_for_status()
        logger.info("Created label '%s' with color %s", name, color)


def get_mr_details(
    server_url: str,
    project_id: str,
    mr_iid: str,
    token: str,
) -> dict:
    """Fetch MR details including author and assignees."""
    url = f"{server_url}/api/v4/projects/{project_id}/merge_requests/{mr_iid}"
    headers = {"PRIVATE-TOKEN": token}
    response = requests.get(url, headers=headers, timeout=30)
    response.raise_for_status()
    return response.json()


def update_mr(
    server_url: str,
    project_id: str,
    mr_iid: str,
    token: str,
    payload: dict,
) -> None:
    """
    Update a merge request via the GitLab API.

    Uses PUT /projects/:id/merge_requests/:iid
    """
    url = f"{server_url}/api/v4/projects/{project_id}/merge_requests/{mr_iid}"
    headers = {"PRIVATE-TOKEN": token}
    response = requests.put(url, headers=headers, json=payload, timeout=30)
    response.raise_for_status()


def get_pending_review_count(
    server_url: str,
    project_id: str,
    token: str,
    user_id: int,
) -> int:
    """
    Count the number of open MRs where the user is assigned as reviewer
    and has NOT yet approved (i.e. review is still pending).

    Fetches open MRs where the user is reviewer, then checks the approval
    state of each to exclude already-approved ones.
    """
    url = f"{server_url}/api/v4/projects/{project_id}/merge_requests"
    headers = {"PRIVATE-TOKEN": token}
    params = {
        "reviewer_id": user_id,
        "state": "opened",
        "per_page": 100,
    }
    response = requests.get(url, headers=headers, params=params, timeout=30)
    response.raise_for_status()
    open_mrs = response.json()

    pending_count = 0
    for mr in open_mrs:
        mr_iid = mr["iid"]
        approvals_url = (
            f"{server_url}/api/v4/projects/{project_id}"
            f"/merge_requests/{mr_iid}/approvals"
        )
        approvals_resp = requests.get(approvals_url, headers=headers, timeout=30)
        if approvals_resp.status_code != 200:
            # If we can't check approvals, assume the review is pending
            pending_count += 1
            continue

        approvals_data = approvals_resp.json()
        approved_by_ids = {
            a.get("user", {}).get("id")
            for a in approvals_data.get("approved_by", [])
        }

        if user_id not in approved_by_ids:
            pending_count += 1

    return pending_count


def pick_available_reviewer(
    server_url: str,
    project_id: str,
    token: str,
    candidate_ids: set[int],
) -> Optional[int]:
    """
    Pick a single reviewer from candidates who has zero pending reviews.

    A pending review is an open MR where the user is assigned as reviewer
    but has not yet approved.

    Returns the user ID of the chosen reviewer, or None if all candidates
    already have pending reviews.
    """
    available: list[int] = []
    for user_id in candidate_ids:
        count = get_pending_review_count(server_url, project_id, token, user_id)
        logger.info("User %d has %d pending review(s)", user_id, count)
        if count == 0:
            available.append(user_id)

    if available:
        return random.choice(available)

    return None


def main() -> None:
    server_url = get_env("CI_SERVER_URL", "https://gitlab.com").rstrip("/")
    project_id = get_env("CI_PROJECT_ID")
    mr_iid = get_env("CI_MERGE_REQUEST_IID")
    token = get_env("GITLAB_TOKEN")

    # Determine repo root (in CI this is the working directory)
    repo_root = os.getcwd()

    # Step 1: Parse CODEOWNERS
    rules = parse_codeowners(repo_root)
    logger.info("Loaded %d CODEOWNERS rules", len(rules))

    # Step 2: Get changed files from MR
    changed_files = get_mr_changed_files(server_url, project_id, mr_iid, token)
    logger.info("MR !%s has %d changed files", mr_iid, len(changed_files))

    # Step 3: Fetch MR details (author + current assignees)
    mr_data = get_mr_details(server_url, project_id, mr_iid, token)
    author_id = mr_data.get("author", {}).get("id")
    current_assignees = mr_data.get("assignees", [])

    # Step 3a: Auto-assign the MR to its author if no assignee yet
    if not current_assignees and author_id:
        logger.info("No assignee on MR -- assigning to author (user ID %d)", author_id)
        update_mr(server_url, project_id, mr_iid, token, {"assignee_ids": [author_id]})

    # Step 3b: Auto-label based on modified directories
    if changed_files:
        new_labels = match_labels(changed_files)
        if new_labels:
            current_labels = {label for label in mr_data.get("labels", [])}
            labels_to_add = new_labels - current_labels
            if labels_to_add:
                ensure_labels_exist(server_url, project_id, token, labels_to_add)
                merged_labels = sorted(current_labels | new_labels)
                update_mr(server_url, project_id, mr_iid, token, {"labels": ",".join(merged_labels)})
                logger.info("Labels set on MR: %s (added: %s)", merged_labels, sorted(labels_to_add))
            else:
                logger.info("Labels already up to date: %s", sorted(current_labels))

    # Step 4: Skip reviewer assignment for draft MRs
    if mr_data.get("draft", False):
        logger.info("MR is a draft -- skipping reviewer assignment")
        return

    if not changed_files:
        logger.info("No changed files found, skipping reviewer assignment")
        return

    # Step 4b: Skip if reviewers are already assigned (manual assignment takes precedence)
    current_reviewers = mr_data.get("reviewers", [])
    if current_reviewers:
        logger.info(
            "MR already has %d reviewer(s) -- skipping automatic assignment",
            len(current_reviewers),
        )
        return

    # Step 5: Match owners
    owners = match_owners(changed_files, rules)
    logger.info("Matched owners: %s", owners)

    if not owners:
        logger.info("No CODEOWNERS rules matched, skipping reviewer assignment")
        return

    # Step 6: Resolve usernames to user IDs
    all_reviewer_ids: set[int] = set()
    for owner in owners:
        user_id = resolve_user(server_url, owner, token)
        if user_id is not None:
            all_reviewer_ids.add(user_id)

    # Step 7: Exclude the MR author from reviewers
    if author_id and author_id in all_reviewer_ids:
        logger.info("Excluding MR author (user ID %d) from reviewers", author_id)
        all_reviewer_ids.discard(author_id)

    if not all_reviewer_ids:
        logger.info("No reviewers to assign after resolving groups and excluding author")
        return

    # Step 8: Pick a single reviewer with no pending reviews
    chosen_reviewer = pick_available_reviewer(
        server_url, project_id, token, all_reviewer_ids
    )

    if chosen_reviewer is None:
        logger.warning(
            "All %d candidate reviewers already have pending reviews -- no reviewer assigned",
            len(all_reviewer_ids),
        )
        return

    # Step 9: Assign the single reviewer
    update_mr(server_url, project_id, mr_iid, token, {"reviewer_ids": [chosen_reviewer]})
    logger.info("Done -- assigned reviewer (user ID %d) to MR !%s", chosen_reviewer, mr_iid)


if __name__ == "__main__":
    main()
