#!/usr/bin/env python3
"""
Assign merge request reviewers based on CODEOWNERS file.

This script reproduces GitLab Premium CODEOWNERS behavior on GitLab Free.
It reads the .gitlab/CODEOWNERS file, matches modified files from the MR
against ownership patterns, resolves group members via the GitLab API,
and assigns them as reviewers on the merge request.

Required environment variables:
  CI_PROJECT_ID        - GitLab project ID (set automatically by CI)
  CI_MERGE_REQUEST_IID - MR internal ID (set automatically by CI)
  REVIEWER_BOT_TOKEN   - GitLab API token with api scope
  CI_SERVER_URL        - GitLab server URL (default: https://gitlab.com)
  CI_PROJECT_PATH      - Full project path, e.g. chapsmind/chapsmind
"""

import fnmatch
import logging
import os
import sys
from pathlib import Path
from typing import Optional
from urllib.parse import quote_plus

import requests

logging.basicConfig(
    level=logging.INFO,
    format="%(levelname)s: %(message)s",
)
logger = logging.getLogger(__name__)

CODEOWNERS_PATH = ".gitlab/CODEOWNERS"


def get_env(name: str, default: Optional[str] = None) -> str:
    """Retrieve a required environment variable or exit with an error."""
    value = os.environ.get(name, default)
    if value is None:
        logger.error("Missing required environment variable: %s", name)
        sys.exit(1)
    return value


def parse_codeowners(repo_root: str) -> list[tuple[str, str]]:
    """
    Parse the CODEOWNERS file and return a list of (pattern, owner) tuples.

    Blank lines and comments (lines starting with #) are skipped.
    Each valid line is expected to have the format: <path-pattern> <@owner>
    """
    codeowners_file = Path(repo_root) / CODEOWNERS_PATH
    if not codeowners_file.exists():
        logger.error("CODEOWNERS file not found at %s", codeowners_file)
        sys.exit(1)

    rules: list[tuple[str, str]] = []
    for line in codeowners_file.read_text(encoding="utf-8").splitlines():
        stripped = line.strip()
        if not stripped or stripped.startswith("#"):
            continue
        parts = stripped.split()
        if len(parts) >= 2:
            pattern = parts[0]
            owner = parts[1]
            rules.append((pattern, owner))
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
    rules: list[tuple[str, str]],
) -> set[str]:
    """
    Match changed files against CODEOWNERS rules and return the set of owners.

    A CODEOWNERS pattern like `apps/front/` matches any file whose path
    starts with that prefix. Glob patterns (e.g. `*.py`) use fnmatch.
    """
    matched_owners: set[str] = set()
    for file_path in changed_files:
        for pattern, owner in rules:
            # Directory-style pattern: match if the file is under that directory
            if pattern.endswith("/") and file_path.startswith(pattern):
                matched_owners.add(owner)
            # Glob-style pattern
            elif fnmatch.fnmatch(file_path, pattern):
                matched_owners.add(owner)
    return matched_owners


def resolve_group_members(
    server_url: str,
    project_path: str,
    group_ref: str,
    token: str,
) -> list[int]:
    """
    Resolve a CODEOWNERS group reference (e.g. @chapsmind/team-front) to
    a list of GitLab user IDs.

    The group_ref format is @namespace/group-name. We strip the leading @
    and query the GitLab Groups API.
    """
    # Strip leading @ to get the full group path
    group_path = group_ref.lstrip("@")
    encoded_path = quote_plus(group_path)

    url = f"{server_url}/api/v4/groups/{encoded_path}/members"
    headers = {"PRIVATE-TOKEN": token}
    response = requests.get(url, headers=headers, timeout=30)

    if response.status_code == 404:
        logger.warning(
            "Group '%s' not found -- it may be a user reference or the token "
            "lacks permission. Trying as a direct user lookup.",
            group_path,
        )
        return resolve_user(server_url, group_ref, token)

    response.raise_for_status()
    members = response.json()
    return [member["id"] for member in members if member.get("id")]


def resolve_user(
    server_url: str,
    user_ref: str,
    token: str,
) -> list[int]:
    """
    Resolve a single @username to a GitLab user ID.
    """
    username = user_ref.lstrip("@")
    # If it contains a slash, it is a group path, not a username
    if "/" in username:
        logger.warning("Cannot resolve '%s' as a user (contains '/')", username)
        return []

    url = f"{server_url}/api/v4/users"
    headers = {"PRIVATE-TOKEN": token}
    params = {"username": username}
    response = requests.get(url, headers=headers, params=params, timeout=30)
    response.raise_for_status()
    users = response.json()

    if users:
        return [users[0]["id"]]
    logger.warning("User '%s' not found on GitLab", username)
    return []


def get_mr_author_id(
    server_url: str,
    project_id: str,
    mr_iid: str,
    token: str,
) -> Optional[int]:
    """Fetch the MR author user ID to exclude them from reviewers."""
    url = f"{server_url}/api/v4/projects/{project_id}/merge_requests/{mr_iid}"
    headers = {"PRIVATE-TOKEN": token}
    response = requests.get(url, headers=headers, timeout=30)
    response.raise_for_status()
    data = response.json()
    author = data.get("author", {})
    return author.get("id")


def assign_reviewers(
    server_url: str,
    project_id: str,
    mr_iid: str,
    reviewer_ids: list[int],
    token: str,
) -> None:
    """
    Assign reviewers to a merge request via the GitLab API.

    Uses PUT /projects/:id/merge_requests/:iid
    """
    url = f"{server_url}/api/v4/projects/{project_id}/merge_requests/{mr_iid}"
    headers = {"PRIVATE-TOKEN": token}
    payload = {"reviewer_ids": reviewer_ids}
    response = requests.put(url, headers=headers, json=payload, timeout=30)
    response.raise_for_status()
    logger.info("Successfully assigned reviewers: %s", reviewer_ids)


def main() -> None:
    server_url = get_env("CI_SERVER_URL", "https://gitlab.com").rstrip("/")
    project_id = get_env("CI_PROJECT_ID")
    mr_iid = get_env("CI_MERGE_REQUEST_IID")
    token = get_env("REVIEWER_BOT_TOKEN")
    project_path = get_env("CI_PROJECT_PATH", "")

    # Determine repo root (in CI this is the working directory)
    repo_root = os.getcwd()

    # Step 1: Parse CODEOWNERS
    rules = parse_codeowners(repo_root)
    logger.info("Loaded %d CODEOWNERS rules", len(rules))

    # Step 2: Get changed files from MR
    changed_files = get_mr_changed_files(server_url, project_id, mr_iid, token)
    logger.info("MR !%s has %d changed files", mr_iid, len(changed_files))

    if not changed_files:
        logger.info("No changed files found, nothing to do")
        return

    # Step 3: Match owners
    owners = match_owners(changed_files, rules)
    logger.info("Matched owners: %s", owners)

    if not owners:
        logger.info("No CODEOWNERS rules matched, skipping reviewer assignment")
        return

    # Step 4: Resolve group/user references to user IDs
    all_reviewer_ids: set[int] = set()
    for owner in owners:
        user_ids = resolve_group_members(server_url, project_path, owner, token)
        all_reviewer_ids.update(user_ids)

    # Step 5: Exclude the MR author from reviewers
    author_id = get_mr_author_id(server_url, project_id, mr_iid, token)
    if author_id and author_id in all_reviewer_ids:
        logger.info("Excluding MR author (user ID %d) from reviewers", author_id)
        all_reviewer_ids.discard(author_id)

    if not all_reviewer_ids:
        logger.info("No reviewers to assign after resolving groups and excluding author")
        return

    # Step 6: Assign reviewers
    assign_reviewers(server_url, project_id, mr_iid, list(all_reviewer_ids), token)
    logger.info("Done -- assigned %d reviewers to MR !%s", len(all_reviewer_ids), mr_iid)


if __name__ == "__main__":
    main()
