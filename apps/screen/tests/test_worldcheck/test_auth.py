"""Tests for WorldCheck HMAC-SHA256 authentication module."""

import base64
import hashlib
import hmac

from app.infrastructure.worldcheck.auth import build_auth_headers


class TestBuildAuthHeaders:
    """Tests for the HMAC-SHA256 signature builder."""

    API_KEY = "test-api-key-12345"
    API_SECRET = "test-api-secret-67890"
    HOST = "api.risk.lseg.com"
    DATE = "Thu, 27 Feb 2026 14:30:00 GMT"

    def test_post_request_signature(self):
        """Test signature generation for a POST request."""
        body = '{"groupId":"group1","entityType":"ORGANISATION","name":"Acme Corp","providerTypes":["WATCHLIST"]}'
        path = "/screening/v3/cases/screeningRequest"

        headers = build_auth_headers(
            method="POST",
            path=path,
            api_key=self.API_KEY,
            api_secret=self.API_SECRET,
            host=self.HOST,
            body=body,
            date=self.DATE,
        )

        assert "Authorization" in headers
        assert "Date" in headers
        assert "Content-Type" in headers
        assert "Content-Length" in headers

        assert headers["Date"] == self.DATE
        assert headers["Content-Type"] == "application/json"
        assert headers["Content-Length"] == str(len(body.encode("utf-8")))

        # Verify Authorization header format
        auth = headers["Authorization"]
        assert auth.startswith("Signature ")
        assert f'keyId="{self.API_KEY}"' in auth
        assert 'algorithm="hmac-sha256"' in auth
        assert 'headers="(request-target) host date content-type content-length"' in auth
        assert 'signature="' in auth

    def test_get_request_signature(self):
        """Test signature generation for a GET request."""
        path = "/screening/v3/cases/abc123/results"

        headers = build_auth_headers(
            method="GET",
            path=path,
            api_key=self.API_KEY,
            api_secret=self.API_SECRET,
            host=self.HOST,
            date=self.DATE,
        )

        assert "Authorization" in headers
        assert "Date" in headers
        assert "Content-Type" in headers
        # GET requests should NOT have Content-Length
        assert "Content-Length" not in headers

        auth = headers["Authorization"]
        assert 'headers="(request-target) host date"' in auth

    def test_post_signature_includes_body(self):
        """Verify POST signing text includes the full request body.

        WorldCheck diverges from HTTP Signature RFC by including
        the full body in the signing text after headers.
        """
        path = "/screening/v3/cases/screeningRequest"
        body = '{"name":"test"}'
        content_length = str(len(body.encode("utf-8")))

        # Manually compute expected signature WITH body
        data_to_sign = (
            f"(request-target): post {path}\n"
            f"host: {self.HOST}\n"
            f"date: {self.DATE}\n"
            f"content-type: application/json\n"
            f"content-length: {content_length}\n"
            f"{body}"
        )
        expected_signature = base64.b64encode(
            hmac.new(
                self.API_SECRET.encode("utf-8"),
                data_to_sign.encode("utf-8"),
                hashlib.sha256,
            ).digest()
        ).decode("utf-8")

        headers = build_auth_headers(
            method="POST",
            path=path,
            api_key=self.API_KEY,
            api_secret=self.API_SECRET,
            host=self.HOST,
            body=body,
            date=self.DATE,
        )

        # Extract signature from Authorization header
        auth = headers["Authorization"]
        sig_start = auth.index('signature="') + len('signature="')
        sig_end = auth.index('"', sig_start)
        actual_signature = auth[sig_start:sig_end]

        assert actual_signature == expected_signature

    def test_different_bodies_produce_different_signatures(self):
        """Verify that different body content produces different HMAC signatures."""
        path = "/test"

        headers_a = build_auth_headers(
            method="POST",
            path=path,
            api_key=self.API_KEY,
            api_secret=self.API_SECRET,
            body='{"name":"Alice"}',
            date=self.DATE,
        )

        headers_b = build_auth_headers(
            method="POST",
            path=path,
            api_key=self.API_KEY,
            api_secret=self.API_SECRET,
            body='{"name":"Bob"}',
            date=self.DATE,
        )

        # Different bodies MUST produce different signatures
        assert headers_a["Authorization"] != headers_b["Authorization"]

    def test_newline_separator_is_lf_only(self):
        """Verify data-to-sign uses LF (0x0A) not CRLF."""
        path = "/test"

        headers = build_auth_headers(
            method="GET",
            path=path,
            api_key=self.API_KEY,
            api_secret=self.API_SECRET,
            host=self.HOST,
            date=self.DATE,
        )

        # Build what the data-to-sign should be
        data_to_sign = f"(request-target): get {path}\nhost: {self.HOST}\ndate: {self.DATE}"

        # Verify no \r\n in the string
        assert "\r\n" not in data_to_sign
        assert "\n" in data_to_sign

        # Verify the signature matches with LF separators
        expected_signature = base64.b64encode(
            hmac.new(
                self.API_SECRET.encode("utf-8"),
                data_to_sign.encode("utf-8"),
                hashlib.sha256,
            ).digest()
        ).decode("utf-8")

        auth = headers["Authorization"]
        sig_start = auth.index('signature="') + len('signature="')
        sig_end = auth.index('"', sig_start)
        actual_signature = auth[sig_start:sig_end]

        assert actual_signature == expected_signature

    def test_content_length_utf8_bytes(self):
        """Verify Content-Length is computed in UTF-8 bytes, not characters."""
        body = '{"name":"Société Générale"}'
        # 'é' is 2 bytes in UTF-8, so byte length > char length
        expected_length = len(body.encode("utf-8"))
        assert expected_length > len(body)  # Sanity check

        headers = build_auth_headers(
            method="POST",
            path="/test",
            api_key=self.API_KEY,
            api_secret=self.API_SECRET,
            body=body,
            date=self.DATE,
        )

        assert headers["Content-Length"] == str(expected_length)

    def test_auto_generates_date_when_none(self):
        """Test that date is auto-generated when not provided."""
        headers = build_auth_headers(
            method="GET",
            path="/test",
            api_key=self.API_KEY,
            api_secret=self.API_SECRET,
        )

        assert "Date" in headers
        # RFC 1123 format includes 'GMT'
        assert "GMT" in headers["Date"]

    def test_method_is_lowercased_in_signature(self):
        """Verify HTTP method is lowercased in (request-target)."""
        path = "/test"

        headers_upper = build_auth_headers(
            method="POST",
            path=path,
            api_key=self.API_KEY,
            api_secret=self.API_SECRET,
            body="{}",
            date=self.DATE,
        )

        headers_lower = build_auth_headers(
            method="post",
            path=path,
            api_key=self.API_KEY,
            api_secret=self.API_SECRET,
            body="{}",
            date=self.DATE,
        )

        # Both should produce the same signature
        assert headers_upper["Authorization"] == headers_lower["Authorization"]

    def test_lseg_doc_format_compliance(self):
        """Verify Authorization header matches LSEG doc format.

        Expected format:
        Signature keyId="{API-KEY}",algorithm="hmac-sha256",
            headers="(request-target) host date content-type content-length",
            signature="{BASE64-HMAC-VALUE}"
        """
        headers = build_auth_headers(
            method="POST",
            path="/screening/v2/cases",
            api_key="4321",
            api_secret="1234",
            host="api.risk.lseg.com",
            body='{"test": true}',
            date="Tue, 07 Jun 2016 20:51:35 GMT",
        )

        auth = headers["Authorization"]
        # Verify exact format components
        assert auth.startswith('Signature keyId="4321",')
        assert 'algorithm="hmac-sha256",' in auth
        assert 'headers="(request-target) host date content-type content-length",' in auth
        assert 'signature="' in auth
        assert auth.endswith('"')
