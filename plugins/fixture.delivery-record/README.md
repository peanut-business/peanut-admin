# Fixture Delivery Record Plugin Artifact

This local fixture has no archive. Its `source.sha256` uses
`canonical-plugin-contents-v1`: enumerate every regular file below each backend
Module root declared by `modules` and its matching `web/src/modules/<module-slug>`
frontend root, excluding `plugin.json`; sort repository-relative POSIX paths
bytewise; concatenate each `path`, a NUL byte, that file's lowercase hex SHA-256,
and an LF; then SHA-256 the resulting bytes.

`manifest_sha256` is the SHA-256 of `plugin.json`. Composer identity SHA-256 is
the hash of the Module-owned `composer.json`; npm integrity is the base64 form
of the SHA-256 of the Module-owned `package.json`; each frontend SHA-256 hashes
the entry file named by that identity.
