# Security status

Known vulnerabilities in **main**: its lock files, and the `:dev` image built from it,
found by `composer audit`, `npm audit` and [Trivy](https://trivy.dev). A release carries
this file as it was when it was tagged. Written every week by
[a scheduled workflow](.github/workflows/security-status.yml) - do not edit by hand.

Updated: 2026-10-05 12:01 UTC

Main at: `a4ffb52`

| Source | Critical | High | Medium | Low | Unknown |
|--------|---------:|-----:|-------:|----:|--------:|
| PHP · ci4/composer.lock | 0 | 0 | 0 | 0 | 0 |
| JavaScript · vue/package-lock.json | 0 | 0 | 0 | 1 | 0 |
| Image · 4spaces/kubernetes-service-orchestrator:dev | 1 | 41 | 201 | 33 | 10 |

## PHP · ci4/composer.lock

*abandoned: vierbergenlars/php-semver*

Nothing known.

## JavaScript · vue/package-lock.json


<details><summary>1 low or unknown</summary>

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| low | [GHSA-gfhx-hw2g-v5hg](https://github.com/advisories/GHSA-gfhx-hw2g-v5hg) | serialize-javascript | 7.1.1 | not >=7.1.1 <7.1.2 | Serialize JavaScript: Cross-site scripting (XSS) via unescaped </script> in serialized function bodies |

</details>

## Image · 4spaces/kubernetes-service-orchestrator:dev

*built from a4ffb52, the commit above · alpine 3.24.2 · 4spaces/kubernetes-service-orchestrator@sha256:ac4b6ca72aa689673a467542c194808939d7ac595bdd4f62157c554c78c921e4*

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| critical | [CVE-2026-13221](https://avd.aquasec.com/nvd/cve-2026-13221) | perl | 5.42.2-r0 | 5.42.2-r1 | perl: Perl: Incorrect regular expression processing via large regular expressions |
| high | [CVE-2026-46729](https://avd.aquasec.com/nvd/cve-2026-46729) | apache2 | 2.4.68-r0 | 2.4.69-r0 | httpd: httpd: mod_heartmonitor: Denial of Service via NULL pointer dereference |
| high | [CVE-2026-56153](https://avd.aquasec.com/nvd/cve-2026-56153) | apache2 | 2.4.68-r0 | 2.4.69-r0 | httpd: httpd: Denial of Service via heap-based buffer overflow in mod_charset_lite |
| high | [CVE-2026-59685](https://avd.aquasec.com/nvd/cve-2026-59685) | apache2 | 2.4.68-r0 | 2.4.69-r0 | httpd: httpd: Denial of Service via out-of-bounds write during Windows path expansion |
| high | [CVE-2026-63292](https://avd.aquasec.com/nvd/cve-2026-63292) | apache2 | 2.4.68-r0 | 2.4.69-r0 | httpd: httpd: Arbitrary code execution via oversized Host header in mod_vhost_alias |
| high | [CVE-2026-63686](https://avd.aquasec.com/nvd/cve-2026-63686) | apache2 | 2.4.68-r0 | 2.4.69-r0 | httpd: httpd: Denial of Service via charset conversion failure in mod_xml2enc |
| high | [CVE-2026-63718](https://avd.aquasec.com/nvd/cve-2026-63718) | apache2 | 2.4.68-r0 | 2.4.69-r0 | httpd: httpd: HTTP response smuggling via crafted Transfer-Encoding response in mod_proxy_uwsgi |
| high | [CVE-2026-73636](https://avd.aquasec.com/nvd/cve-2026-73636) | apache2 | 2.4.68-r0 | 2.4.69-r0 | httpd: httpd: Authentication bypass via credential replay in mod_auth_digest |
| high | [CVE-2026-84304](https://avd.aquasec.com/nvd/cve-2026-84304) | google.golang.org/grpc | v1.82.1 | 1.83.1 | gRPC-Go is the Go language implementation of gRPC. Prior to 1.83.1, in ... |
| high | [CVE-2026-84445](https://avd.aquasec.com/nvd/cve-2026-84445) | google.golang.org/grpc | v1.82.1 | 1.82.2, 1.83.2, 1.84.0-dev.0.20260825144003-d5a41119e0e3, 1.85.0-dev.0.20260825072537-93e31b48545e | google.golang.org/grpc: gRPC-Go: Denial of Service via malformed RPC requests |
| high | [CVE-2026-93990](https://avd.aquasec.com/nvd/cve-2026-93990) | libexpat | 2.8.4-r0 | 2.8.5-r0 | expat: Expat: XML Injection via Malformed UTF-16 Input |
| high | [CVE-2026-103111](https://avd.aquasec.com/nvd/cve-2026-103111) | pcre2 | 10.48-r0 | 10.49-r0 | pcre2: pcre2: Out-of-bounds write via crafted regular expression |
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
| high | [CVE-2026-19553](https://avd.aquasec.com/nvd/cve-2026-19553) | pyc | 3.14.7-r1 | 3.14.8-r0 | python: python: Certificate verification bypass via missing server_hostname validation in SSLContext.wrap_bio() |
| high | [CVE-2026-82049](https://avd.aquasec.com/nvd/cve-2026-82049) | pyc | 3.14.7-r1 | 3.14.8-r0 | python: Python tarfile module: File modification and content disclosure via crafted archives |
| high | [CVE-2026-19553](https://avd.aquasec.com/nvd/cve-2026-19553) | python3 | 3.14.7-r1 | 3.14.8-r0 | python: python: Certificate verification bypass via missing server_hostname validation in SSLContext.wrap_bio() |
| high | [CVE-2026-82049](https://avd.aquasec.com/nvd/cve-2026-82049) | python3 | 3.14.7-r1 | 3.14.8-r0 | python: Python tarfile module: File modification and content disclosure via crafted archives |
| high | [CVE-2026-19553](https://avd.aquasec.com/nvd/cve-2026-19553) | python3-pyc | 3.14.7-r1 | 3.14.8-r0 | python: python: Certificate verification bypass via missing server_hostname validation in SSLContext.wrap_bio() |
| high | [CVE-2026-82049](https://avd.aquasec.com/nvd/cve-2026-82049) | python3-pyc | 3.14.7-r1 | 3.14.8-r0 | python: Python tarfile module: File modification and content disclosure via crafted archives |
| high | [CVE-2026-19553](https://avd.aquasec.com/nvd/cve-2026-19553) | python3-pycache-pyc0 | 3.14.7-r1 | 3.14.8-r0 | python: python: Certificate verification bypass via missing server_hostname validation in SSLContext.wrap_bio() |
| high | [CVE-2026-82049](https://avd.aquasec.com/nvd/cve-2026-82049) | python3-pycache-pyc0 | 3.14.7-r1 | 3.14.8-r0 | python: Python tarfile module: File modification and content disclosure via crafted archives |
| medium | [CVE-2026-42528](https://avd.aquasec.com/nvd/cve-2026-42528) | apache2 | 2.4.68-r0 | 2.4.69-r0 | httpd: httpd: Denial of Service via mod_dav shared lock memory calculation error |
| medium | [CVE-2026-58415](https://avd.aquasec.com/nvd/cve-2026-58415) | apache2 | 2.4.68-r0 | 2.4.69-r0 | httpd: httpd: Information disclosure via direct request to the WebDAV state directory |
| medium | [CVE-2026-63045](https://avd.aquasec.com/nvd/cve-2026-63045) | apache2 | 2.4.68-r0 | 2.4.69-r0 | httpd: httpd: unauthorized connection to arbitrary hosts via crafted FTP PASV response |
| medium | [CVE-2026-73637](https://avd.aquasec.com/nvd/cve-2026-73637) | apache2 | 2.4.68-r0 | 2.4.69-r0 | httpd: httpd: Authentication state corruption via concurrent Digest authentication requests |
| medium | [CVE-2026-79768](https://avd.aquasec.com/nvd/cve-2026-79768) | apache2 | 2.4.68-r0 | 2.4.69-r0 | httpd: httpd: Information disclosure in mod_userdir via single-dot path equivalence |
| medium | [CVE-2026-93546](https://avd.aquasec.com/nvd/cve-2026-93546) | apache2 | 2.4.68-r0 | 2.4.69-r0 | httpd: httpd: Denial of Service via integer overflow in mod_dav_fs |
| medium | [CVE-2026-53493](https://avd.aquasec.com/nvd/cve-2026-53493) | github.com/containerd/containerd/v2 | v2.3.3 | 2.0.13, 2.2.9, 2.3.6, 2.4.1 | containerd is an open-source container runtime. Prior to versions 1.7. ... |
| medium | [CVE-2026-53495](https://avd.aquasec.com/nvd/cve-2026-53495) | github.com/containerd/containerd/v2 | v2.3.3 | 2.0.12, 2.2.8, 2.3.5 | github.com/containerd/containerd: containerd: Denial of Service via CRI ExecSync goroutine leak |
| medium | [CVE-2026-56855](https://avd.aquasec.com/nvd/cve-2026-56855) | golang.org/x/crypto | v0.55.0 | 0.56.0 | golang.org/x/crypto/ssh: golang.org/x/crypto/ssh: Denial of Service via crafted messages |
| medium | [CVE-2026-78662](https://avd.aquasec.com/nvd/cve-2026-78662) | golang.org/x/crypto | v0.55.0 | 0.56.0 | golang.org/x/crypto/ssh: golang.org/x/crypto/ssh: Denial of Service via channel request flooding |
| medium | [CVE-2026-84303](https://avd.aquasec.com/nvd/cve-2026-84303) | google.golang.org/grpc | v1.82.1 | 1.83.1 | gRPC-Go is the Go language implementation of gRPC. Prior to 1.83.1, th ... |
| medium | [CVE-2026-58055](https://avd.aquasec.com/nvd/cve-2026-58055) | nghttp2-dev | 1.69.0-r0 | 1.70.0-r0 | nghttp2: nghttp2: HTTP Request/Response Smuggling and Response-Queue Poisoning via ambiguous HTTP/1.1 Upgrade requests |
| medium | [CVE-2026-58055](https://avd.aquasec.com/nvd/cve-2026-58055) | nghttp2-libs | 1.69.0-r0 | 1.70.0-r0 | nghttp2: nghttp2: HTTP Request/Response Smuggling and Response-Queue Poisoning via ambiguous HTTP/1.1 Upgrade requests |
| medium | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-apache2 | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Denial of Service via heap buffer overflow in SOAP client |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-apache2 | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-apache2 | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-apache2 | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-apache2 | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-apache2 | 8.5.10-r0 | 8.5.11-r0 | php: php: Server impersonation via Common Name fallback in TLS verification |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-apache2 | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-apache2 | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-bcmath | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Denial of Service via heap buffer overflow in SOAP client |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-bcmath | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-bcmath | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-bcmath | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-bcmath | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-bcmath | 8.5.10-r0 | 8.5.11-r0 | php: php: Server impersonation via Common Name fallback in TLS verification |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-bcmath | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-bcmath | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-calendar | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Denial of Service via heap buffer overflow in SOAP client |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-calendar | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-calendar | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-calendar | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-calendar | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-calendar | 8.5.10-r0 | 8.5.11-r0 | php: php: Server impersonation via Common Name fallback in TLS verification |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-calendar | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-calendar | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-common | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Denial of Service via heap buffer overflow in SOAP client |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-common | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-common | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-common | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-common | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-common | 8.5.10-r0 | 8.5.11-r0 | php: php: Server impersonation via Common Name fallback in TLS verification |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-common | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-common | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-ctype | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Denial of Service via heap buffer overflow in SOAP client |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-ctype | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-ctype | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-ctype | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-ctype | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-ctype | 8.5.10-r0 | 8.5.11-r0 | php: php: Server impersonation via Common Name fallback in TLS verification |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-ctype | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-ctype | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-curl | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Denial of Service via heap buffer overflow in SOAP client |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-curl | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-curl | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-curl | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-curl | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-curl | 8.5.10-r0 | 8.5.11-r0 | php: php: Server impersonation via Common Name fallback in TLS verification |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-curl | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-curl | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-gd | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Denial of Service via heap buffer overflow in SOAP client |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-gd | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-gd | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-gd | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-gd | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-gd | 8.5.10-r0 | 8.5.11-r0 | php: php: Server impersonation via Common Name fallback in TLS verification |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-gd | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-gd | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-gmp | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Denial of Service via heap buffer overflow in SOAP client |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-gmp | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-gmp | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-gmp | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-gmp | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-gmp | 8.5.10-r0 | 8.5.11-r0 | php: php: Server impersonation via Common Name fallback in TLS verification |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-gmp | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-gmp | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-intl | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Denial of Service via heap buffer overflow in SOAP client |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-intl | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-intl | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-intl | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-intl | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-intl | 8.5.10-r0 | 8.5.11-r0 | php: php: Server impersonation via Common Name fallback in TLS verification |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-intl | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-intl | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-mbstring | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Denial of Service via heap buffer overflow in SOAP client |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-mbstring | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-mbstring | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-mbstring | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-mbstring | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-mbstring | 8.5.10-r0 | 8.5.11-r0 | php: php: Server impersonation via Common Name fallback in TLS verification |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-mbstring | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-mbstring | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-mysqli | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Denial of Service via heap buffer overflow in SOAP client |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-mysqli | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-mysqli | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-mysqli | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-mysqli | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-mysqli | 8.5.10-r0 | 8.5.11-r0 | php: php: Server impersonation via Common Name fallback in TLS verification |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-mysqli | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-mysqli | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-mysqlnd | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Denial of Service via heap buffer overflow in SOAP client |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-mysqlnd | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-mysqlnd | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-mysqlnd | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-mysqlnd | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-mysqlnd | 8.5.10-r0 | 8.5.11-r0 | php: php: Server impersonation via Common Name fallback in TLS verification |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-mysqlnd | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-mysqlnd | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-openssl | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Denial of Service via heap buffer overflow in SOAP client |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-openssl | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-openssl | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-openssl | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-openssl | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-openssl | 8.5.10-r0 | 8.5.11-r0 | php: php: Server impersonation via Common Name fallback in TLS verification |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-openssl | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-openssl | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-pdo | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Denial of Service via heap buffer overflow in SOAP client |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-pdo | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-pdo | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-pdo | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-pdo | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-pdo | 8.5.10-r0 | 8.5.11-r0 | php: php: Server impersonation via Common Name fallback in TLS verification |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-pdo | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-pdo | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-pdo_mysql | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Denial of Service via heap buffer overflow in SOAP client |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-pdo_mysql | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-pdo_mysql | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-pdo_mysql | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-pdo_mysql | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-pdo_mysql | 8.5.10-r0 | 8.5.11-r0 | php: php: Server impersonation via Common Name fallback in TLS verification |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-pdo_mysql | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-pdo_mysql | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-posix | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Denial of Service via heap buffer overflow in SOAP client |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-posix | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-posix | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-posix | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-posix | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-posix | 8.5.10-r0 | 8.5.11-r0 | php: php: Server impersonation via Common Name fallback in TLS verification |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-posix | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-posix | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-session | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Denial of Service via heap buffer overflow in SOAP client |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-session | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-session | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-session | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-session | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-session | 8.5.10-r0 | 8.5.11-r0 | php: php: Server impersonation via Common Name fallback in TLS verification |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-session | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-session | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-simplexml | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Denial of Service via heap buffer overflow in SOAP client |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-simplexml | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-simplexml | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-simplexml | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-simplexml | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-simplexml | 8.5.10-r0 | 8.5.11-r0 | php: php: Server impersonation via Common Name fallback in TLS verification |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-simplexml | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-simplexml | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-tokenizer | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Denial of Service via heap buffer overflow in SOAP client |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-tokenizer | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-tokenizer | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-tokenizer | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-tokenizer | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-tokenizer | 8.5.10-r0 | 8.5.11-r0 | php: php: Server impersonation via Common Name fallback in TLS verification |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-tokenizer | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-tokenizer | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-xml | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Denial of Service via heap buffer overflow in SOAP client |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-xml | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-xml | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-xml | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-xml | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-xml | 8.5.10-r0 | 8.5.11-r0 | php: php: Server impersonation via Common Name fallback in TLS verification |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-xml | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-xml | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-xmlwriter | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Denial of Service via heap buffer overflow in SOAP client |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-xmlwriter | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-xmlwriter | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-xmlwriter | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-xmlwriter | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-xmlwriter | 8.5.10-r0 | 8.5.11-r0 | php: php: Server impersonation via Common Name fallback in TLS verification |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-xmlwriter | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-xmlwriter | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2025-14181](https://avd.aquasec.com/nvd/cve-2025-14181) | php85-zip | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Denial of Service via heap buffer overflow in SOAP client |
| medium | [CVE-2026-6103](https://avd.aquasec.com/nvd/cve-2026-6103) | php85-zip | 8.5.10-r0 | 8.5.11-r0 | php: PHP: Archive entry injection via integer overflow in TAR parser |
| medium | [CVE-2026-91766](https://avd.aquasec.com/nvd/cve-2026-91766) | php85-zip | 8.5.10-r0 | 8.5.11-r0 | php: php: Credential disclosure via cross-origin HTTP redirects |
| medium | [CVE-2026-91767](https://avd.aquasec.com/nvd/cve-2026-91767) | php85-zip | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via crafted TLS server certificate |
| medium | [CVE-2026-91768](https://avd.aquasec.com/nvd/cve-2026-91768) | php85-zip | 8.5.10-r0 | 8.5.11-r0 | php: php: Access control bypass via partial IPv6 address comparison |
| medium | [CVE-2026-91769](https://avd.aquasec.com/nvd/cve-2026-91769) | php85-zip | 8.5.10-r0 | 8.5.11-r0 | php: php: Server impersonation via Common Name fallback in TLS verification |
| medium | [CVE-2026-92842](https://avd.aquasec.com/nvd/cve-2026-92842) | php85-zip | 8.5.10-r0 | 8.5.11-r0 | php: php: Information disclosure via out-of-bounds read in stream filters |
| medium | [CVE-2026-93682](https://avd.aquasec.com/nvd/cve-2026-93682) | php85-zip | 8.5.10-r0 | 8.5.11-r0 | php: php: Out-of-bounds read via empty HTTP redirect Location header |
| medium | [CVE-2026-15806](https://avd.aquasec.com/nvd/cve-2026-15806) | pyc | 3.14.7-r1 | 3.14.8-r0 | python: Python: Information disclosure due to incorrect URL scheme matching |
| medium | [CVE-2026-17084](https://avd.aquasec.com/nvd/cve-2026-17084) | pyc | 3.14.7-r1 | 3.14.8-r0 | python: Python stringprep module: Incorrect domain name processing breaks IDNA interoperability |
| medium | [CVE-2026-19672](https://avd.aquasec.com/nvd/cve-2026-19672) | pyc | 3.14.7-r1 | 3.14.8-r0 | python: Python tarfile module: Directory traversal allows creation of empty directories outside extraction destination |
| medium | [CVE-2026-15806](https://avd.aquasec.com/nvd/cve-2026-15806) | python3 | 3.14.7-r1 | 3.14.8-r0 | python: Python: Information disclosure due to incorrect URL scheme matching |
| medium | [CVE-2026-17084](https://avd.aquasec.com/nvd/cve-2026-17084) | python3 | 3.14.7-r1 | 3.14.8-r0 | python: Python stringprep module: Incorrect domain name processing breaks IDNA interoperability |
| medium | [CVE-2026-19672](https://avd.aquasec.com/nvd/cve-2026-19672) | python3 | 3.14.7-r1 | 3.14.8-r0 | python: Python tarfile module: Directory traversal allows creation of empty directories outside extraction destination |
| medium | [CVE-2026-15806](https://avd.aquasec.com/nvd/cve-2026-15806) | python3-pyc | 3.14.7-r1 | 3.14.8-r0 | python: Python: Information disclosure due to incorrect URL scheme matching |
| medium | [CVE-2026-17084](https://avd.aquasec.com/nvd/cve-2026-17084) | python3-pyc | 3.14.7-r1 | 3.14.8-r0 | python: Python stringprep module: Incorrect domain name processing breaks IDNA interoperability |
| medium | [CVE-2026-19672](https://avd.aquasec.com/nvd/cve-2026-19672) | python3-pyc | 3.14.7-r1 | 3.14.8-r0 | python: Python tarfile module: Directory traversal allows creation of empty directories outside extraction destination |
| medium | [CVE-2026-15806](https://avd.aquasec.com/nvd/cve-2026-15806) | python3-pycache-pyc0 | 3.14.7-r1 | 3.14.8-r0 | python: Python: Information disclosure due to incorrect URL scheme matching |
| medium | [CVE-2026-17084](https://avd.aquasec.com/nvd/cve-2026-17084) | python3-pycache-pyc0 | 3.14.7-r1 | 3.14.8-r0 | python: Python stringprep module: Incorrect domain name processing breaks IDNA interoperability |
| medium | [CVE-2026-19672](https://avd.aquasec.com/nvd/cve-2026-19672) | python3-pycache-pyc0 | 3.14.7-r1 | 3.14.8-r0 | python: Python tarfile module: Directory traversal allows creation of empty directories outside extraction destination |

<details><summary>43 low or unknown</summary>

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| low | [CVE-2026-42356](https://avd.aquasec.com/nvd/cve-2026-42356) | apache2 | 2.4.68-r0 | 2.4.69-r0 | httpd: httpd: arbitrary code execution via incorrect handler assignment during internal CGI redirects |
| low | [CVE-2026-47360](https://avd.aquasec.com/nvd/cve-2026-47360) | apache2 | 2.4.68-r0 | 2.4.69-r0 | httpd: httpd: Information disclosure via session cookie leakage during internal redirects |
| low | [CVE-2026-48005](https://avd.aquasec.com/nvd/cve-2026-48005) | apache2 | 2.4.68-r0 | 2.4.69-r0 | httpd: httpd: Denial of service via forged Authorization headers in mod_auth_digest |
| low | [CVE-2026-56449](https://avd.aquasec.com/nvd/cve-2026-56449) | apache2 | 2.4.68-r0 | 2.4.69-r0 | httpd: httpd: Denial of Service via crafted HTTP response bodies in mod_proxy_html |
| low | [CVE-2026-81870](https://avd.aquasec.com/nvd/cve-2026-81870) | go.opentelemetry.io/otel/exporters/otlp/otlptrace | v1.44.0 | 1.45.0 | github.com/open-telemetry/opentelemetry-go: OpenTelemetry-Go: Information disclosure via exporter configuration logging |
| low | [CVE-2026-81870](https://avd.aquasec.com/nvd/cve-2026-81870) | go.opentelemetry.io/otel/exporters/otlp/otlptrace/otlptracegrpc | v1.44.0 | 1.45.0 | github.com/open-telemetry/opentelemetry-go: OpenTelemetry-Go: Information disclosure via exporter configuration logging |
| low | [CVE-2026-81870](https://avd.aquasec.com/nvd/cve-2026-81870) | go.opentelemetry.io/otel/sdk | v1.44.0 | 1.45.0 | github.com/open-telemetry/opentelemetry-go: OpenTelemetry-Go: Information disclosure via exporter configuration logging |
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
| low | [CVE-2026-15310](https://avd.aquasec.com/nvd/cve-2026-15310) | pyc | 3.14.7-r1 | 3.14.8-r0 | When decompressing crafted zip files using the bzip/LZMA/Zstandard   c ... |
| low | [CVE-2026-15310](https://avd.aquasec.com/nvd/cve-2026-15310) | python3 | 3.14.7-r1 | 3.14.8-r0 | When decompressing crafted zip files using the bzip/LZMA/Zstandard   c ... |
| low | [CVE-2026-15310](https://avd.aquasec.com/nvd/cve-2026-15310) | python3-pyc | 3.14.7-r1 | 3.14.8-r0 | When decompressing crafted zip files using the bzip/LZMA/Zstandard   c ... |
| low | [CVE-2026-15310](https://avd.aquasec.com/nvd/cve-2026-15310) | python3-pycache-pyc0 | 3.14.7-r1 | 3.14.8-r0 | When decompressing crafted zip files using the bzip/LZMA/Zstandard   c ... |
| unknown | [CVE-2026-56154](https://avd.aquasec.com/nvd/cve-2026-56154) | apache2 | 2.4.68-r0 | 2.4.69-r0 | Use After Free vulnerability in Apache HTTP Server's mod_rewrite when  ... |
| unknown | [CVE-2026-57941](https://avd.aquasec.com/nvd/cve-2026-57941) | apache2 | 2.4.68-r0 | 2.4.69-r0 | Use After Free vulnerability in Apache HTTP Server's mod_http2via shar ... |
| unknown | [CVE-2026-59797](https://avd.aquasec.com/nvd/cve-2026-59797) | apache2 | 2.4.68-r0 | 2.4.69-r0 | Improper Privilege Management vulnerability in Apache HTTP Server's mo ... |
| unknown | GO-2026-5932 | golang.org/x/crypto | v0.55.0 |  | The golang.org/x/crypto/openpgp package is unmaintained, unsafe by design, and has known security issues |
| unknown | [CVE-2026-46675](https://avd.aquasec.com/nvd/cve-2026-46675) | libpng | 1.6.58-r1 | 1.6.59-r0 | [Use-after-free of zlib input in `png_read_end` after incomplete zTXt, iTXt or iCCP decompression] |
| unknown | [CVE-2026-46675](https://avd.aquasec.com/nvd/cve-2026-46675) | libpng-dev | 1.6.58-r1 | 1.6.59-r0 | [Use-after-free of zlib input in `png_read_end` after incomplete zTXt, iTXt or iCCP decompression] |
| unknown | [CVE-2026-19445](https://avd.aquasec.com/nvd/cve-2026-19445) | pyc | 3.14.7-r1 | 3.14.8-r0 | A remote, unauthenticated TLS client can make a server crash or call t ... |
| unknown | [CVE-2026-19445](https://avd.aquasec.com/nvd/cve-2026-19445) | python3 | 3.14.7-r1 | 3.14.8-r0 | A remote, unauthenticated TLS client can make a server crash or call t ... |
| unknown | [CVE-2026-19445](https://avd.aquasec.com/nvd/cve-2026-19445) | python3-pyc | 3.14.7-r1 | 3.14.8-r0 | A remote, unauthenticated TLS client can make a server crash or call t ... |
| unknown | [CVE-2026-19445](https://avd.aquasec.com/nvd/cve-2026-19445) | python3-pycache-pyc0 | 3.14.7-r1 | 3.14.8-r0 | A remote, unauthenticated TLS client can make a server crash or call t ... |

</details>
