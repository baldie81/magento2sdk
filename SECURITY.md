# Security policy

## Supported versions

Security fixes are released for the latest minor version.

## Reporting a vulnerability

Please do **not** open a public issue for a security problem.

Report it privately through GitHub: on this repository open the **Security**
tab and choose **Report a vulnerability**
(<https://github.com/baldie81/magento2sdk/security/advisories/new>). Include
the affected version, what an attacker could do, and a minimal way to
reproduce it.

You can expect an acknowledgement within a few days. Once a fix is released the
advisory is published and you are credited, unless you prefer otherwise.

## What the SDK does to protect credentials

- The base URL must be `https://` unless `allowInsecureHttp` is set explicitly.
- TLS certificates are verified by default.
- Redirects are never followed, so neither the token nor a request body is
  replayed to another scheme or host.
- The bearer token is redacted from `var_dump()`/`print_r()` of the
  `Configuration`, and neither it nor request bodies travel with the SDK's
  exceptions or appear in their stack traces (PHP 8.2+).
