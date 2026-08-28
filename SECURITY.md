# Security Policy

## Supported versions

Security fixes are prepared for the latest published Peanut Admin release. Before reporting,
confirm the affected annotated tag or GitHub Release and whether the behavior still reproduces on
the latest release. Older releases may require an application upgrade before a fix can be applied.

## Reporting a vulnerability

Do not disclose vulnerability details, credentials, personal data, Tenant data, exploit code, or
raw logs in a public GitHub Issue, Discussion, pull request, or screenshot.

Use **Security → Report a vulnerability** on the Peanut Admin GitHub repository when that private
form is available. If it is not available, open a public Issue containing only:

- the title `Security contact request`;
- the affected release or tag;
- a way for the maintainers to contact you privately.

Do not include the vulnerable component, attack steps, impact details, proof of concept, secrets,
or diagnostic bundle in that contact-only Issue. Wait until a maintainer establishes a private
channel before sending any sensitive material.

For an ordinary, non-sensitive defect, use the public Issue flow described in the
[consumer support guide](docs-site/support.md).

## What to include privately

- affected release/tag, complete source commit when known, and deployment mode;
- vulnerable component and prerequisites;
- minimal reproduction and observed impact;
- whether the issue crosses Account, Tenant, permission, Module, file, or deployment boundaries;
- a redacted diagnostic bundle only when it is necessary.

Maintainers will acknowledge receipt through the same private channel, assess the supported release,
and coordinate remediation and disclosure. Do not test against systems, data, accounts, or Providers
you do not own or have explicit permission to use.
