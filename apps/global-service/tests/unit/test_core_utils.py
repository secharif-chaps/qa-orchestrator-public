"""Tests for core utility functions."""

import pytest

from app.core.utils import calculate_pages


class TestCalculatePages:
    """Tests for the calculate_pages pagination helper."""

    def test_exact_pages(self):
        """Test when total is exactly divisible by size."""
        assert calculate_pages(100, 10) == 10
        assert calculate_pages(50, 25) == 2
        assert calculate_pages(1000, 100) == 10

    def test_partial_page(self):
        """Test when there's a partial page at the end."""
        assert calculate_pages(105, 10) == 11
        assert calculate_pages(51, 25) == 3
        assert calculate_pages(1001, 100) == 11

    def test_single_page(self):
        """Test when total is less than page size."""
        assert calculate_pages(5, 10) == 1
        assert calculate_pages(1, 100) == 1
        assert calculate_pages(99, 100) == 1

    def test_empty_result(self):
        """Test when there are no items."""
        assert calculate_pages(0, 10) == 0
        assert calculate_pages(0, 100) == 0
        assert calculate_pages(0, 1) == 0

    def test_edge_cases(self):
        """Test edge cases."""
        assert calculate_pages(1, 1) == 1
        assert calculate_pages(2, 1) == 2
        assert calculate_pages(1000, 1) == 1000
