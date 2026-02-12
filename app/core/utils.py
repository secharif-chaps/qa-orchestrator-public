"""Core utility functions for the application."""


def calculate_pages(total: int, size: int) -> int:
    """Calculate total number of pages for pagination.

    Args:
        total: Total number of items
        size: Number of items per page

    Returns:
        Total number of pages (0 if no items)

    Examples:
        >>> calculate_pages(100, 10)
        10
        >>> calculate_pages(105, 10)
        11
        >>> calculate_pages(0, 10)
        0
    """
    return (total + size - 1) // size if total > 0 else 0
