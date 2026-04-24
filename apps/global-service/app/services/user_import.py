"""Service for bulk user import operations.

This module provides functionality to import multiple users
into Keycloak, with support for:
- Duplicate detection (email and username)
- Password generation
- Organization assignment
- Permission granting (organization.read)
- Partial success (some rows can fail while others succeed)
"""

from fastapi import HTTPException, status
from pydantic import ValidationError

from app.core.logging_config import get_logger
from app.schemas.user import (
    BulkUserImportRequest,
    BulkUserImportResponse,
    UserImportResult,
    UserImportRow,
)
from app.services.keycloak_admin import keycloak_admin_service

logger = get_logger(__name__)


async def check_existing_users(
    users: list[UserImportRow],
) -> tuple[dict[str, str], dict[str, str]]:
    """Check for existing users in Keycloak by email and username.

    Returns:
        Tuple of (email_to_user_id, username_to_user_id) dictionaries
        for existing users
    """
    # Note: This fetches up to 10000 users. For larger deployments,
    # consider implementing paginated checks or individual lookups.
    existing_users = await keycloak_admin_service.get_users(first=0, max_results=10000)

    email_to_user_id: dict[str, str] = {}
    username_to_user_id: dict[str, str] = {}

    for kc_user in existing_users:
        email = kc_user.get("email", "").lower()
        username = kc_user.get("username", "").lower()
        user_id = kc_user.get("id")

        if email:
            email_to_user_id[email] = user_id
        if username:
            username_to_user_id[username] = user_id

    return email_to_user_id, username_to_user_id


async def check_duplicates_in_import(
    users: list[UserImportRow],
) -> tuple[set[int], dict[str, list[int]]]:
    """Check for duplicate emails/usernames within the import itself.

    Returns:
        Tuple of:
        - Set of row indices that are duplicates
        - Dict mapping email/username to list of row indices where it appears
    """
    email_occurrences: dict[str, list[int]] = {}
    username_occurrences: dict[str, list[int]] = {}
    duplicate_rows: set[int] = set()

    for idx, user in enumerate(users):
        email_lower = user.email.lower()
        username_lower = user.username.lower()

        # Track email occurrences
        if email_lower not in email_occurrences:
            email_occurrences[email_lower] = []
        email_occurrences[email_lower].append(idx)

        # Track username occurrences
        if username_lower not in username_occurrences:
            username_occurrences[username_lower] = []
        username_occurrences[username_lower].append(idx)

    # Mark duplicate rows (keep first occurrence, mark rest as duplicates)
    for indices in email_occurrences.values():
        if len(indices) > 1:
            duplicate_rows.update(indices[1:])  # Skip first occurrence

    for indices in username_occurrences.values():
        if len(indices) > 1:
            duplicate_rows.update(indices[1:])

    # Return occurrences for error messages
    duplicates_info = {}
    duplicates_info.update({f"email:{k}": v for k, v in email_occurrences.items() if len(v) > 1})
    duplicates_info.update({f"username:{k}": v for k, v in username_occurrences.items() if len(v) > 1})

    return duplicate_rows, duplicates_info


async def import_users_bulk(
    request: BulkUserImportRequest,
    admin_username: str,
) -> BulkUserImportResponse:
    """Import multiple users into Keycloak.

    This function:
    1. Validates all users before creating any
    2. Checks for duplicate emails/usernames against existing Keycloak users
    3. Checks for duplicates within the import itself
    4. Creates users with temporary passwords (forces change on first login)
    5. Assigns users to the specified organization
    6. Grants organization.read permission to all imported users
    7. Returns detailed results per row (success/failure with error messages)

    Args:
        request: Bulk import request with organization_id, users, and generate_passwords flag
        admin_username: Username of the admin performing the import (for logging)

    Returns:
        BulkUserImportResponse with success/error counts and per-row results

    Raises:
        HTTPException: If organization doesn't exist or other critical errors
    """
    logger.info(
        "Starting bulk user import",
        extra={
            "admin_user": admin_username,
            "organization_id": request.organization_id,
            "user_count": len(request.users),
            "generate_passwords": request.generate_passwords,
        },
    )

    # Verify organization exists
    try:
        org = await keycloak_admin_service.get_organization(request.organization_id)
        if not org:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND, detail=f"Organization {request.organization_id} not found"
            )
        org_name = org.get("name", request.organization_id)
    except HTTPException:
        raise
    except Exception as e:
        logger.error("Failed to verify organization", exc_info=e, extra={"organization_id": request.organization_id})
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND, detail=f"Organization {request.organization_id} not found"
        )

    results: list[UserImportResult] = []
    success_count = 0
    error_count = 0

    # Step 1: Check for existing users in Keycloak
    existing_emails, existing_usernames = await check_existing_users(request.users)

    # Step 2: Check for duplicates within the import
    internal_duplicate_rows, internal_duplicates_info = await check_duplicates_in_import(request.users)

    if internal_duplicates_info:
        logger.warning("Found duplicates within import file", extra={"duplicates": internal_duplicates_info})

    # Step 3: Process each user
    for idx, user in enumerate(request.users):
        result = UserImportResult(
            row_index=idx,
            username=user.username,
            email=user.email,
            success=False,
        )

        try:
            # Check for internal duplicates (within the import file)
            if idx in internal_duplicate_rows:
                result.error_message = "Duplicate email or username in import file (keeping first occurrence)"
                results.append(result)
                error_count += 1
                continue

            # Check for existing email in Keycloak
            email_lower = user.email.lower()
            if email_lower in existing_emails:
                result.error_message = f"Email '{user.email}' already exists"
                results.append(result)
                error_count += 1
                continue

            # Check for existing username in Keycloak
            username_lower = user.username.lower()
            if username_lower in existing_usernames:
                result.error_message = f"Username '{user.username}' already exists"
                results.append(result)
                error_count += 1
                continue

            # Determine password
            # generate_passwords=true means generate for ALL users (override provided passwords)
            # generate_passwords=false means use provided passwords, but still generate for users without one
            if request.generate_passwords:
                password = keycloak_admin_service.generate_temp_password(length=12)
                result.generated_password = password
            elif user.password:
                password = user.password
            else:
                password = keycloak_admin_service.generate_temp_password(length=12)
                result.generated_password = password

            # Create user in Keycloak
            logger.info(
                "Creating user in Keycloak",
                extra={
                    "username": user.username,
                    "email": user.email,
                    "row_index": idx,
                },
            )

            user_data = {
                "username": user.username,
                "email": user.email,
                "firstName": user.firstname or "",
                "lastName": user.lastname or "",
                "temporaryPassword": password,
            }

            created_user = await keycloak_admin_service.create_user(user_data)
            user_id = created_user.get("id")

            if not user_id:
                result.error_message = "Failed to create user: no user ID returned"
                results.append(result)
                error_count += 1
                continue

            result.user_id = user_id

            # Add user to organization
            org_success = await keycloak_admin_service.add_user_to_organization(
                organization_id=request.organization_id,
                user_id=user_id,
            )

            if not org_success:
                logger.warning(
                    "User created but organization assignment failed",
                    extra={
                        "user_id": user_id,
                        "organization_id": request.organization_id,
                    },
                )
                result.error_message = "User created but organization assignment failed"

            # Grant organization.read permission
            await keycloak_admin_service.sync_user_realm_roles(
                user_id=user_id,
                target_roles=["organization.read"],
            )

            # Track this email/username as now existing (for internal duplicate check)
            existing_emails[email_lower] = user_id
            existing_usernames[username_lower] = user_id

            result.success = True
            results.append(result)
            success_count += 1

            logger.info(
                "Successfully imported user",
                extra={
                    "user_id": user_id,
                    "username": user.username,
                    "row_index": idx,
                },
            )

        except HTTPException as e:
            error_detail = e.detail if hasattr(e, "detail") else str(e)
            result.error_message = error_detail
            results.append(result)
            error_count += 1
            logger.warning(
                "HTTP error importing user",
                extra={
                    "username": user.username,
                    "error": error_detail,
                    "row_index": idx,
                },
            )

        except ValidationError as e:
            result.error_message = str(e)
            results.append(result)
            error_count += 1
            logger.warning(
                "Validation error importing user",
                extra={
                    "username": user.username,
                    "error": str(e),
                    "row_index": idx,
                },
            )

        except Exception as e:
            result.error_message = f"Unexpected error: {str(e)}"
            results.append(result)
            error_count += 1
            logger.error(
                "Unexpected error importing user",
                exc_info=e,
                extra={
                    "username": user.username,
                    "row_index": idx,
                },
            )

    logger.info(
        "Bulk user import completed",
        extra={
            "admin_user": admin_username,
            "organization_id": request.organization_id,
            "organization_name": org_name,
            "total_count": len(request.users),
            "success_count": success_count,
            "error_count": error_count,
        },
    )

    return BulkUserImportResponse(
        success_count=success_count,
        error_count=error_count,
        total_count=len(request.users),
        results=results,
    )
