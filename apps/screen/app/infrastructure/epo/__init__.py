"""EPO OPS (Open Patent Services) API integration.

Async OAuth2 client for the European Patent Office OPS API v3.2.
Credentials (Consumer Key + Consumer Secret) are supplied by the caller,
typically decrypted from `OrganizationFeatureFlag.config` via the
`services.feature_flags.get_feature_config` helper.
"""
