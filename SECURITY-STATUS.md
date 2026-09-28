# Security status

Known vulnerabilities in **main**: its lock files, and the `:dev` image built from it,
found by `composer audit`, `npm audit` and [Trivy](https://trivy.dev). A release carries
this file as it was when it was tagged. Written every week by
[a scheduled workflow](.github/workflows/security-status.yml) - do not edit by hand.

Updated: 2026-09-28 11:25 UTC

Main at: `7cd31e6`

| Source | Critical | High | Medium | Low | Unknown |
|--------|---------:|-----:|-------:|----:|--------:|
| PHP · ci4/composer.lock | 0 | 0 | 0 | 0 | 0 |
| JavaScript · vue/package-lock.json | 0 | 0 | 0 | 0 | 0 |
| Image · 4spaces/kubernetes-service-orchestrator:dev | 1 | 25 | 137 | 25 | 45 |

## PHP · ci4/composer.lock

*abandoned: vierbergenlars/php-semver*

Nothing known.

## JavaScript · vue/package-lock.json

Nothing known.

## Image · 4spaces/kubernetes-service-orchestrator:dev

*built from 7cd31e6, the commit above · alpine 3.24.2 · 4spaces/kubernetes-service-orchestrator@sha256:5d7493f0edc606c4a3f09880791001074e9a902ebb6c37b8019696b9c80e2313*

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| critical | [CVE-2026-13221](https://avd.aquasec.com/nvd/cve-2026-13221) | perl | 5.42.2-r0 | 5.42.2-r1 | perl: Perl: Incorrect regular expression processing via large regular expressions |
| high | [CVE-2026-84304](https://avd.aquasec.com/nvd/cve-2026-84304) | google.golang.org/grpc | v1.82.1 | 1.83.1 | gRPC-Go is the Go language implementation of gRPC. Prior to 1.83.1, in ... |
| high | [CVE-2026-84445](https://avd.aquasec.com/nvd/cve-2026-84445) | google.golang.org/grpc | v1.82.1 | 1.82.2, 1.83.2, 1.84.0-dev.0.20260825144003-d5a41119e0e3, 1.85.0-dev.0.20260825072537-93e31b48545e | google.golang.org/grpc: gRPC-Go: Denial of Service via malformed RPC requests |
| high | [CVE-2026-93990](https://avd.aquasec.com/nvd/cve-2026-93990) | libexpat | 2.8.4-r0 | 2.8.5-r0 | expat: Expat: XML Injection via Malformed UTF-16 Input |
| high | [CVE-2026-91765](https://avd.aquasec.com/nvd/cve-2026-91765) | php85-apache2 | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via unbounded recursion in SOAP parser |
| high | [CVE-2026-91765](https://avd.aquasec.com/nvd/cve-2026-91765) | php85-bcmath | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via unbounded recursion in SOAP parser |
| high | [CVE-2026-91765](https://avd.aquasec.com/nvd/cve-2026-91765) | php85-calendar | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via unbounded recursion in SOAP parser |
| high | [CVE-2026-91765](https://avd.aquasec.com/nvd/cve-2026-91765) | php85-common | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via unbounded recursion in SOAP parser |
| high | [CVE-2026-91765](https://avd.aquasec.com/nvd/cve-2026-91765) | php85-ctype | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via unbounded recursion in SOAP parser |
| high | [CVE-2026-91765](https://avd.aquasec.com/nvd/cve-2026-91765) | php85-curl | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via unbounded recursion in SOAP parser |
| high | [CVE-2026-91765](https://avd.aquasec.com/nvd/cve-2026-91765) | php85-gd | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via unbounded recursion in SOAP parser |
| high | [CVE-2026-91765](https://avd.aquasec.com/nvd/cve-2026-91765) | php85-gmp | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via unbounded recursion in SOAP parser |
| high | [CVE-2026-91765](https://avd.aquasec.com/nvd/cve-2026-91765) | php85-intl | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via unbounded recursion in SOAP parser |
| high | [CVE-2026-91765](https://avd.aquasec.com/nvd/cve-2026-91765) | php85-mbstring | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via unbounded recursion in SOAP parser |
| high | [CVE-2026-91765](https://avd.aquasec.com/nvd/cve-2026-91765) | php85-mysqli | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via unbounded recursion in SOAP parser |
| high | [CVE-2026-91765](https://avd.aquasec.com/nvd/cve-2026-91765) | php85-mysqlnd | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via unbounded recursion in SOAP parser |
| high | [CVE-2026-91765](https://avd.aquasec.com/nvd/cve-2026-91765) | php85-openssl | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via unbounded recursion in SOAP parser |
| high | [CVE-2026-91765](https://avd.aquasec.com/nvd/cve-2026-91765) | php85-pdo | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via unbounded recursion in SOAP parser |
| high | [CVE-2026-91765](https://avd.aquasec.com/nvd/cve-2026-91765) | php85-pdo_mysql | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via unbounded recursion in SOAP parser |
| high | [CVE-2026-91765](https://avd.aquasec.com/nvd/cve-2026-91765) | php85-posix | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via unbounded recursion in SOAP parser |
| high | [CVE-2026-91765](https://avd.aquasec.com/nvd/cve-2026-91765) | php85-session | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via unbounded recursion in SOAP parser |
| high | [CVE-2026-91765](https://avd.aquasec.com/nvd/cve-2026-91765) | php85-simplexml | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via unbounded recursion in SOAP parser |
| high | [CVE-2026-91765](https://avd.aquasec.com/nvd/cve-2026-91765) | php85-tokenizer | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via unbounded recursion in SOAP parser |
| high | [CVE-2026-91765](https://avd.aquasec.com/nvd/cve-2026-91765) | php85-xml | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via unbounded recursion in SOAP parser |
| high | [CVE-2026-91765](https://avd.aquasec.com/nvd/cve-2026-91765) | php85-xmlwriter | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via unbounded recursion in SOAP parser |
| high | [CVE-2026-91765](https://avd.aquasec.com/nvd/cve-2026-91765) | php85-zip | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via unbounded recursion in SOAP parser |
| medium | [CVE-2026-53493](https://avd.aquasec.com/nvd/cve-2026-53493) | github.com/containerd/containerd/v2 | v2.3.3 | 2.0.13, 2.2.9, 2.3.6, 2.4.1 | containerd is an open-source container runtime. Prior to versions 1.7. ... |
| medium | [CVE-2026-53495](https://avd.aquasec.com/nvd/cve-2026-53495) | github.com/containerd/containerd/v2 | v2.3.3 | 2.0.12, 2.2.8, 2.3.5 | github.com/containerd/containerd: containerd: Denial of Service via CRI ExecSync goroutine leak |
| medium | [CVE-2026-56855](https://avd.aquasec.com/nvd/cve-2026-56855) | golang.org/x/crypto | v0.55.0 | 0.56.0 | golang.org/x/crypto/ssh: golang.org/x/crypto/ssh: Denial of Service via crafted messages |
| medium | [CVE-2026-78662](https://avd.aquasec.com/nvd/cve-2026-78662) | golang.org/x/crypto | v0.55.0 | 0.56.0 | golang.org/x/crypto/ssh: golang.org/x/crypto/ssh: Denial of Service via channel request flooding |
| medium | [CVE-2026-84303](https://avd.aquasec.com/nvd/cve-2026-84303) | google.golang.org/grpc | v1.82.1 | 1.83.1 | gRPC-Go is the Go language implementation of gRPC. Prior to 1.83.1, th ... |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-apache2 | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-apache2 | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-apache2 | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-apache2 | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-apache2 | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-apache2 | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-bcmath | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-bcmath | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-bcmath | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-bcmath | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-bcmath | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-bcmath | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-calendar | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-calendar | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-calendar | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-calendar | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-calendar | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-calendar | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-common | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-common | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-common | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-common | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-common | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-common | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-ctype | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-ctype | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-ctype | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-ctype | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-ctype | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-ctype | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-curl | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-curl | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-curl | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-curl | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-curl | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-curl | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-gd | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-gd | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-gd | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-gd | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-gd | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-gd | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-gmp | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-gmp | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-gmp | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-gmp | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-gmp | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-gmp | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-intl | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-intl | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-intl | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-intl | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-intl | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-intl | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-mbstring | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-mbstring | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-mbstring | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-mbstring | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-mbstring | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-mbstring | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-mysqli | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-mysqli | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-mysqli | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-mysqli | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-mysqli | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-mysqli | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-mysqlnd | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-mysqlnd | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-mysqlnd | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-mysqlnd | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-mysqlnd | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-mysqlnd | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-openssl | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-openssl | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-openssl | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-openssl | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-openssl | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-openssl | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-pdo | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-pdo | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-pdo | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-pdo | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-pdo | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-pdo | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-pdo_mysql | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-pdo_mysql | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-pdo_mysql | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-pdo_mysql | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-pdo_mysql | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-pdo_mysql | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-posix | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-posix | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-posix | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-posix | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-posix | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-posix | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-session | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-session | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-session | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-session | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-session | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-session | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-simplexml | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-simplexml | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-simplexml | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-simplexml | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-simplexml | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-simplexml | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-tokenizer | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-tokenizer | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-tokenizer | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-tokenizer | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-tokenizer | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-tokenizer | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-xml | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-xml | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-xml | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-xml | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-xml | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-xml | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-xmlwriter | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-xmlwriter | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-xmlwriter | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-xmlwriter | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-xmlwriter | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-xmlwriter | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-zip | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-zip | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-zip | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-zip | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-zip | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-zip | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |

<details><summary>70 low or unknown</summary>

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| low | [CVE-2026-81870](https://avd.aquasec.com/nvd/cve-2026-81870) | go.opentelemetry.io/otel/exporters/otlp/otlptrace | v1.44.0 | 1.45.0 | OpenTelemetry-Go is the Go implementation of OpenTelemetry. From versi ... |
| low | [CVE-2026-81870](https://avd.aquasec.com/nvd/cve-2026-81870) | go.opentelemetry.io/otel/exporters/otlp/otlptrace/otlptracegrpc | v1.44.0 | 1.45.0 | OpenTelemetry-Go is the Go implementation of OpenTelemetry. From versi ... |
| low | [CVE-2026-81870](https://avd.aquasec.com/nvd/cve-2026-81870) | go.opentelemetry.io/otel/sdk | v1.44.0 | 1.45.0 | OpenTelemetry-Go is the Go implementation of OpenTelemetry. From versi ... |
| low | [CVE-2025-1218](https://avd.aquasec.com/nvd/cve-2025-1218) | php85-apache2 | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via out-of-bounds read in mysqlnd wire protocol parser |
| low | [CVE-2025-1218](https://avd.aquasec.com/nvd/cve-2025-1218) | php85-bcmath | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via out-of-bounds read in mysqlnd wire protocol parser |
| low | [CVE-2025-1218](https://avd.aquasec.com/nvd/cve-2025-1218) | php85-calendar | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via out-of-bounds read in mysqlnd wire protocol parser |
| low | [CVE-2025-1218](https://avd.aquasec.com/nvd/cve-2025-1218) | php85-common | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via out-of-bounds read in mysqlnd wire protocol parser |
| low | [CVE-2025-1218](https://avd.aquasec.com/nvd/cve-2025-1218) | php85-ctype | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via out-of-bounds read in mysqlnd wire protocol parser |
| low | [CVE-2025-1218](https://avd.aquasec.com/nvd/cve-2025-1218) | php85-curl | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via out-of-bounds read in mysqlnd wire protocol parser |
| low | [CVE-2025-1218](https://avd.aquasec.com/nvd/cve-2025-1218) | php85-gd | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via out-of-bounds read in mysqlnd wire protocol parser |
| low | [CVE-2025-1218](https://avd.aquasec.com/nvd/cve-2025-1218) | php85-gmp | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via out-of-bounds read in mysqlnd wire protocol parser |
| low | [CVE-2025-1218](https://avd.aquasec.com/nvd/cve-2025-1218) | php85-intl | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via out-of-bounds read in mysqlnd wire protocol parser |
| low | [CVE-2025-1218](https://avd.aquasec.com/nvd/cve-2025-1218) | php85-mbstring | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via out-of-bounds read in mysqlnd wire protocol parser |
| low | [CVE-2025-1218](https://avd.aquasec.com/nvd/cve-2025-1218) | php85-mysqli | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via out-of-bounds read in mysqlnd wire protocol parser |
| low | [CVE-2025-1218](https://avd.aquasec.com/nvd/cve-2025-1218) | php85-mysqlnd | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via out-of-bounds read in mysqlnd wire protocol parser |
| low | [CVE-2025-1218](https://avd.aquasec.com/nvd/cve-2025-1218) | php85-openssl | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via out-of-bounds read in mysqlnd wire protocol parser |
| low | [CVE-2025-1218](https://avd.aquasec.com/nvd/cve-2025-1218) | php85-pdo | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via out-of-bounds read in mysqlnd wire protocol parser |
| low | [CVE-2025-1218](https://avd.aquasec.com/nvd/cve-2025-1218) | php85-pdo_mysql | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via out-of-bounds read in mysqlnd wire protocol parser |
| low | [CVE-2025-1218](https://avd.aquasec.com/nvd/cve-2025-1218) | php85-posix | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via out-of-bounds read in mysqlnd wire protocol parser |
| low | [CVE-2025-1218](https://avd.aquasec.com/nvd/cve-2025-1218) | php85-session | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via out-of-bounds read in mysqlnd wire protocol parser |
| low | [CVE-2025-1218](https://avd.aquasec.com/nvd/cve-2025-1218) | php85-simplexml | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via out-of-bounds read in mysqlnd wire protocol parser |
| low | [CVE-2025-1218](https://avd.aquasec.com/nvd/cve-2025-1218) | php85-tokenizer | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via out-of-bounds read in mysqlnd wire protocol parser |
| low | [CVE-2025-1218](https://avd.aquasec.com/nvd/cve-2025-1218) | php85-xml | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via out-of-bounds read in mysqlnd wire protocol parser |
| low | [CVE-2025-1218](https://avd.aquasec.com/nvd/cve-2025-1218) | php85-xmlwriter | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via out-of-bounds read in mysqlnd wire protocol parser |
| low | [CVE-2025-1218](https://avd.aquasec.com/nvd/cve-2025-1218) | php85-zip | 8.5.10-r0 | 8.5.11-r0 | php: php: Denial of Service via out-of-bounds read in mysqlnd wire protocol parser |
| unknown | GO-2026-5932 | golang.org/x/crypto | v0.55.0 |  | The golang.org/x/crypto/openpgp package is unmaintained, unsafe by design, and has known security issues |
| unknown | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-apache2 | 8.5.10-r0 | 8.5.11-r0 | The SOAP HTTP client guards its response buffer growth with a check th ... |
| unknown | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-apache2 | 8.5.10-r0 | 8.5.11-r0 | PHP's OpenSSL stream peer verification checks the certificate's subjec ... |
| unknown | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-bcmath | 8.5.10-r0 | 8.5.11-r0 | The SOAP HTTP client guards its response buffer growth with a check th ... |
| unknown | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-bcmath | 8.5.10-r0 | 8.5.11-r0 | PHP's OpenSSL stream peer verification checks the certificate's subjec ... |
| unknown | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-calendar | 8.5.10-r0 | 8.5.11-r0 | The SOAP HTTP client guards its response buffer growth with a check th ... |
| unknown | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-calendar | 8.5.10-r0 | 8.5.11-r0 | PHP's OpenSSL stream peer verification checks the certificate's subjec ... |
| unknown | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-common | 8.5.10-r0 | 8.5.11-r0 | The SOAP HTTP client guards its response buffer growth with a check th ... |
| unknown | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-common | 8.5.10-r0 | 8.5.11-r0 | PHP's OpenSSL stream peer verification checks the certificate's subjec ... |
| unknown | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-ctype | 8.5.10-r0 | 8.5.11-r0 | The SOAP HTTP client guards its response buffer growth with a check th ... |
| unknown | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-ctype | 8.5.10-r0 | 8.5.11-r0 | PHP's OpenSSL stream peer verification checks the certificate's subjec ... |
| unknown | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-curl | 8.5.10-r0 | 8.5.11-r0 | The SOAP HTTP client guards its response buffer growth with a check th ... |
| unknown | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-curl | 8.5.10-r0 | 8.5.11-r0 | PHP's OpenSSL stream peer verification checks the certificate's subjec ... |
| unknown | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-gd | 8.5.10-r0 | 8.5.11-r0 | The SOAP HTTP client guards its response buffer growth with a check th ... |
| unknown | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-gd | 8.5.10-r0 | 8.5.11-r0 | PHP's OpenSSL stream peer verification checks the certificate's subjec ... |
| unknown | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-gmp | 8.5.10-r0 | 8.5.11-r0 | The SOAP HTTP client guards its response buffer growth with a check th ... |
| unknown | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-gmp | 8.5.10-r0 | 8.5.11-r0 | PHP's OpenSSL stream peer verification checks the certificate's subjec ... |
| unknown | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-intl | 8.5.10-r0 | 8.5.11-r0 | The SOAP HTTP client guards its response buffer growth with a check th ... |
| unknown | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-intl | 8.5.10-r0 | 8.5.11-r0 | PHP's OpenSSL stream peer verification checks the certificate's subjec ... |
| unknown | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-mbstring | 8.5.10-r0 | 8.5.11-r0 | The SOAP HTTP client guards its response buffer growth with a check th ... |
| unknown | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-mbstring | 8.5.10-r0 | 8.5.11-r0 | PHP's OpenSSL stream peer verification checks the certificate's subjec ... |
| unknown | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-mysqli | 8.5.10-r0 | 8.5.11-r0 | The SOAP HTTP client guards its response buffer growth with a check th ... |
| unknown | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-mysqli | 8.5.10-r0 | 8.5.11-r0 | PHP's OpenSSL stream peer verification checks the certificate's subjec ... |
| unknown | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-mysqlnd | 8.5.10-r0 | 8.5.11-r0 | The SOAP HTTP client guards its response buffer growth with a check th ... |
| unknown | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-mysqlnd | 8.5.10-r0 | 8.5.11-r0 | PHP's OpenSSL stream peer verification checks the certificate's subjec ... |
| unknown | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-openssl | 8.5.10-r0 | 8.5.11-r0 | The SOAP HTTP client guards its response buffer growth with a check th ... |
| unknown | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-openssl | 8.5.10-r0 | 8.5.11-r0 | PHP's OpenSSL stream peer verification checks the certificate's subjec ... |
| unknown | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-pdo | 8.5.10-r0 | 8.5.11-r0 | The SOAP HTTP client guards its response buffer growth with a check th ... |
| unknown | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-pdo | 8.5.10-r0 | 8.5.11-r0 | PHP's OpenSSL stream peer verification checks the certificate's subjec ... |
| unknown | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-pdo_mysql | 8.5.10-r0 | 8.5.11-r0 | The SOAP HTTP client guards its response buffer growth with a check th ... |
| unknown | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-pdo_mysql | 8.5.10-r0 | 8.5.11-r0 | PHP's OpenSSL stream peer verification checks the certificate's subjec ... |
| unknown | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-posix | 8.5.10-r0 | 8.5.11-r0 | The SOAP HTTP client guards its response buffer growth with a check th ... |
| unknown | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-posix | 8.5.10-r0 | 8.5.11-r0 | PHP's OpenSSL stream peer verification checks the certificate's subjec ... |
| unknown | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-session | 8.5.10-r0 | 8.5.11-r0 | The SOAP HTTP client guards its response buffer growth with a check th ... |
| unknown | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-session | 8.5.10-r0 | 8.5.11-r0 | PHP's OpenSSL stream peer verification checks the certificate's subjec ... |
| unknown | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-simplexml | 8.5.10-r0 | 8.5.11-r0 | The SOAP HTTP client guards its response buffer growth with a check th ... |
| unknown | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-simplexml | 8.5.10-r0 | 8.5.11-r0 | PHP's OpenSSL stream peer verification checks the certificate's subjec ... |
| unknown | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-tokenizer | 8.5.10-r0 | 8.5.11-r0 | The SOAP HTTP client guards its response buffer growth with a check th ... |
| unknown | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-tokenizer | 8.5.10-r0 | 8.5.11-r0 | PHP's OpenSSL stream peer verification checks the certificate's subjec ... |
| unknown | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-xml | 8.5.10-r0 | 8.5.11-r0 | The SOAP HTTP client guards its response buffer growth with a check th ... |
| unknown | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-xml | 8.5.10-r0 | 8.5.11-r0 | PHP's OpenSSL stream peer verification checks the certificate's subjec ... |
| unknown | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-xmlwriter | 8.5.10-r0 | 8.5.11-r0 | The SOAP HTTP client guards its response buffer growth with a check th ... |
| unknown | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-xmlwriter | 8.5.10-r0 | 8.5.11-r0 | PHP's OpenSSL stream peer verification checks the certificate's subjec ... |
| unknown | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-zip | 8.5.10-r0 | 8.5.11-r0 | The SOAP HTTP client guards its response buffer growth with a check th ... |
| unknown | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-zip | 8.5.10-r0 | 8.5.11-r0 | PHP's OpenSSL stream peer verification checks the certificate's subjec ... |

</details>
