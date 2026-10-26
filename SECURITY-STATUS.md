# Security status

Known vulnerabilities in **main**: its lock files, and the `:dev` image built from it,
found by `composer audit`, `npm audit` and [Trivy](https://trivy.dev). A release carries
this file as it was when it was tagged. Written every week by
[a scheduled workflow](.github/workflows/security-status.yml) - do not edit by hand.

Updated: 2026-10-26 10:44 UTC

Main at: `13b4cab`

| Source | Critical | High | Medium | Low | Unknown |
|--------|---------:|-----:|-------:|----:|--------:|
| PHP · ci4/composer.lock | 0 | 0 | 0 | 0 | 0 |
| JavaScript · vue/package-lock.json | 1 | 4 | 6 | 5 | 0 |
| Image · 4spaces/kubernetes-service-orchestrator:dev | 0 | 15 | 22 | 70 | 2 |

## PHP · ci4/composer.lock

*abandoned: vierbergenlars/php-semver*

Nothing known.

## JavaScript · vue/package-lock.json

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| critical | [GHSA-pqg4-j6r4-53mv](https://github.com/advisories/GHSA-pqg4-j6r4-53mv) | shell-quote | 1.10.0 | not >=1.8.4 <1.11.0 | shell-quote: `quote()` command injection via a line terminator in a token after a `{ comment }` token |
| high | [GHSA-8r9p-f939-6h3c](https://github.com/advisories/GHSA-8r9p-f939-6h3c) | follow-redirects | 1.16.0 | not >=1.16.0 <1.16.1 | follow-redirects sensitiveHeaders regex escaping can expose credential headers |
| high | [GHSA-q3jh-wrc6-jgq8](https://github.com/advisories/GHSA-q3jh-wrc6-jgq8) | follow-redirects | 1.16.0 | not >=1.16.0 <1.16.1 | follow-redirects array-form headers bypass cross-origin credential stripping |
| high | [GHSA-68fv-2mgg-jv7q](https://github.com/advisories/GHSA-68fv-2mgg-jv7q) | source-map-js | 1.2.1 | not >=1.0.0 <1.2.2 | source-map-js allows event-loop denial of service through indexed source-map section offsets |
| high | [GHSA-587h-7wmj-h5pm](https://github.com/advisories/GHSA-587h-7wmj-h5pm) | vite | 8.3.0 | not >=8.3.0 <=8.3.2 | launch-editor: `fs.existsSync()` existence check can be bypassed on Windows |
| medium | [GHSA-w74x-v6f9-j3xg](https://github.com/advisories/GHSA-w74x-v6f9-j3xg) | fast-uri | 3.1.8 | not >=3.0.0 <3.1.9 | fast-uri vulnerable to URI scheme spoofing via percent-encoded scheme decoding |
| medium | [GHSA-wjr9-j795-jxcr](https://github.com/advisories/GHSA-wjr9-j795-jxcr) | fast-uri | 3.1.8 | not >=3.1.3 <3.1.9 | fast-uri vulnerable to host confusion via unchecked WHATWG host canonicalization |
| medium | [GHSA-698v-4r3x-3vcv](https://github.com/advisories/GHSA-698v-4r3x-3vcv) | vite | 8.3.0 | not >=8.3.0 <=8.3.2 | launch-editor: escapeCmdArgs() omits % escaping on Windows |
| medium | [GHSA-r825-w8mm-5pxw](https://github.com/advisories/GHSA-r825-w8mm-5pxw) | vite | 8.3.0 | not >=8.3.0 <=8.3.2 | launch-editor: Editor CLI flag misinterpretation causing arbitrary command execution for some editors |
| medium | [GHSA-rq7h-c2jc-7f22](https://github.com/advisories/GHSA-rq7h-c2jc-7f22) | vite | 8.3.0 | not >=8.3.0 <=8.3.2 | Vite: Files outside `server.fs.allow` could be served when project paths match system paths on non-Windows machine |
| medium | [GHSA-vfpm-58rq-9qcg](https://github.com/advisories/GHSA-vfpm-58rq-9qcg) | vite | 8.3.0 | not >=8.3.0 <=8.3.2 | Vite: First 4 bytes of files outside of `server.fs` setting is exposed by `?vite-wasm-instance` |

<details><summary>5 low or unknown</summary>

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| low | [GHSA-333x-w4r6-6crp](https://github.com/advisories/GHSA-333x-w4r6-6crp) | fast-uri | 3.1.8 | not >=3.0.0 <3.1.9 | fast-uri vulnerable to scheme injection via an unescaped first-segment colon in suffix references |
| low | [GHSA-xhq7-xmqj-769h](https://github.com/advisories/GHSA-xhq7-xmqj-769h) | fast-uri | 3.1.8 | not >=3.0.0 <3.1.9 | fast-uri vulnerable to improper path equivalence in relative reference normalization |
| low | [GHSA-gfhx-hw2g-v5hg](https://github.com/advisories/GHSA-gfhx-hw2g-v5hg) | serialize-javascript | 7.1.1 | not >=7.1.1 <7.1.2 | Serialize JavaScript: Cross-site scripting (XSS) via unescaped </script> in serialized function bodies |
| low | [GHSA-m5mm-p8h7-m29v](https://github.com/advisories/GHSA-m5mm-p8h7-m29v) | serialize-javascript | 7.1.1 | not <7.1.3 | Serialize JavaScript: Cross-site scripting (XSS) via unescaped </script at the end of a serialized function body |
| low | [GHSA-9jrq-w75r-8gcw](https://github.com/advisories/GHSA-9jrq-w75r-8gcw) | vite | 8.3.0 | not >=8.3.0 <=8.3.2 | Vite: Cross origin script execution through Vite custom middleware |

</details>

## Image · 4spaces/kubernetes-service-orchestrator:dev

*built from 13b4cab, the commit above · alpine 3.24.2 · 4spaces/kubernetes-service-orchestrator@sha256:5a074d1a3ec2b207335b09eba006ff5f241206af8540b5106eb995778f807332*

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| high | [CVE-2026-111497](https://avd.aquasec.com/nvd/cve-2026-111497) | brotli | 1.2.0-r1 | 1.2.1-r0 | brotli: Out-of-Bounds Write in Google Brotli Decoder |
| high | [CVE-2026-111497](https://avd.aquasec.com/nvd/cve-2026-111497) | brotli-dev | 1.2.0-r1 | 1.2.1-r0 | brotli: Out-of-Bounds Write in Google Brotli Decoder |
| high | [CVE-2026-111497](https://avd.aquasec.com/nvd/cve-2026-111497) | brotli-libs | 1.2.0-r1 | 1.2.1-r0 | brotli: Out-of-Bounds Write in Google Brotli Decoder |
| high | [CVE-2026-92392](https://avd.aquasec.com/nvd/cve-2026-92392) | curl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: HTTP/2 upload Use-After-Free |
| high | [CVE-2026-92392](https://avd.aquasec.com/nvd/cve-2026-92392) | curl-dev | 8.22.0-r0 | 8.23.0-r0 | curl: curl: HTTP/2 upload Use-After-Free |
| high | [CVE-2026-95837](https://avd.aquasec.com/nvd/cve-2026-95837) | github.com/containerd/containerd/v2 | v2.3.3 | 2.2.7, 2.3.4 | github.com/containerd/containerd: containerd: Privilege escalation via crafted container checkpoint restore |
| high | [CVE-2026-111943](https://avd.aquasec.com/nvd/cve-2026-111943) | go | 1.26.9-r0 | 1.26.10-r0 | crypto/x509: golang: crypto/x509: Man-in-the-middle attacks via untrusted intermediate certificates |
| high | [CVE-2026-78669](https://avd.aquasec.com/nvd/cve-2026-78669) | golang.org/x/net | v0.58.0 | 0.60.0 | net/http: net/http/internal/http2: golang.org/x/net/http2: net/http: Denial of Service via excessive HTTP/2 SETTINGS fr… |
| high | [CVE-2026-84304](https://avd.aquasec.com/nvd/cve-2026-84304) | google.golang.org/grpc | v1.82.1 | 1.83.1 | google.golang.org/grpc: gRPC-Go: Denial of Service via HTTP/2 DATA Frame Fragmentation |
| high | [CVE-2026-84445](https://avd.aquasec.com/nvd/cve-2026-84445) | google.golang.org/grpc | v1.82.1 | 1.82.2, 1.83.2, 1.84.0-dev.0.20260825144003-d5a41119e0e3, 1.85.0-dev.0.20260825072537-93e31b48545e | google.golang.org/grpc: gRPC-Go: Denial of Service via malformed RPC requests |
| high | [CVE-2026-92392](https://avd.aquasec.com/nvd/cve-2026-92392) | libcurl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: HTTP/2 upload Use-After-Free |
| high | [CVE-2026-111943](https://avd.aquasec.com/nvd/cve-2026-111943) | stdlib | v1.26.6 | 1.26.10, 1.27.3 | crypto/x509: golang: crypto/x509: Man-in-the-middle attacks via untrusted intermediate certificates |
| high | [CVE-2026-78667](https://avd.aquasec.com/nvd/cve-2026-78667) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | net/http: golang: golang: Denial of Service via crafted HTTP Range headers |
| high | [CVE-2026-78669](https://avd.aquasec.com/nvd/cve-2026-78669) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | net/http: net/http/internal/http2: golang.org/x/net/http2: net/http: Denial of Service via excessive HTTP/2 SETTINGS fr… |
| high | [CVE-2026-97031](https://avd.aquasec.com/nvd/cve-2026-97031) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | crypto/tls: golang: crypto/tls: Denial of Service via multiple ECH outer extension references |
| medium | [CVE-2026-92388](https://avd.aquasec.com/nvd/cve-2026-92388) | curl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: FTP wildcard write callback mixup |
| medium | [CVE-2026-92388](https://avd.aquasec.com/nvd/cve-2026-92388) | curl-dev | 8.22.0-r0 | 8.23.0-r0 | curl: curl: FTP wildcard write callback mixup |
| medium | [CVE-2026-53493](https://avd.aquasec.com/nvd/cve-2026-53493) | github.com/containerd/containerd/v2 | v2.3.3 | 2.0.13, 2.2.9, 2.3.6, 2.4.1 | github.com/containerd/containerd: containerd: Denial of Service via crafted OCI image index |
| medium | [CVE-2026-53495](https://avd.aquasec.com/nvd/cve-2026-53495) | github.com/containerd/containerd/v2 | v2.3.3 | 2.0.12, 2.2.8, 2.3.5 | github.com/containerd/containerd: containerd: Denial of Service via CRI ExecSync goroutine leak |
| medium | [CVE-2026-95838](https://avd.aquasec.com/nvd/cve-2026-95838) | github.com/containerd/containerd/v2 | v2.3.3 | 2.0.12, 2.2.8, 2.3.5 | github.com/containerd/containerd: containerd: Denial of Service via repeated directory traversals during layer unpacking |
| medium | [CVE-2026-56855](https://avd.aquasec.com/nvd/cve-2026-56855) | golang.org/x/crypto | v0.55.0 | 0.56.0 | golang.org/x/crypto/ssh: golang.org/x/crypto/ssh: Denial of Service via crafted messages |
| medium | [CVE-2026-78662](https://avd.aquasec.com/nvd/cve-2026-78662) | golang.org/x/crypto | v0.55.0 | 0.56.0 | golang.org/x/crypto/ssh: golang.org/x/crypto/ssh: Denial of Service via channel request flooding |
| medium | [CVE-2026-78659](https://avd.aquasec.com/nvd/cve-2026-78659) | golang.org/x/net | v0.58.0 | 0.60.0 | net/http: golang.org/x/net/http2: golang: net/http: Denial of Service via HTTP/2 trailer header memory exhaustion |
| medium | [CVE-2026-78660](https://avd.aquasec.com/nvd/cve-2026-78660) | golang.org/x/net | v0.58.0 | 0.60.0 | net/http: golang: golang.org/x/net/http2: net/http: HTTP response smuggling via malformed HTTP/2 framing headers |
| medium | [CVE-2026-78663](https://avd.aquasec.com/nvd/cve-2026-78663) | golang.org/x/net | v0.58.0 | 0.60.0 | net/http: net/http/internal/http2: golang: golang.org/x/net/http2: golang: flow control limit bypass via double refund … |
| medium | [CVE-2026-97032](https://avd.aquasec.com/nvd/cve-2026-97032) | golang.org/x/net | v0.58.0 | 0.60.0 | net/http: net/http/internal/http2: golang: golang.org/x/net/http2: net/http: Denial of Service via concurrent HPACK enc… |
| medium | [CVE-2026-84303](https://avd.aquasec.com/nvd/cve-2026-84303) | google.golang.org/grpc | v1.82.1 | 1.83.1 | gRPC-Go is the Go language implementation of gRPC. Prior to 1.83.1, th ... |
| medium | [CVE-2026-92388](https://avd.aquasec.com/nvd/cve-2026-92388) | libcurl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: FTP wildcard write callback mixup |
| medium | [CVE-2026-56866](https://avd.aquasec.com/nvd/cve-2026-56866) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | net/http: golang: golang: Cross-user response poisoning via desynchronized HTTP CONNECT connections |
| medium | [CVE-2026-78659](https://avd.aquasec.com/nvd/cve-2026-78659) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | net/http: golang.org/x/net/http2: golang: net/http: Denial of Service via HTTP/2 trailer header memory exhaustion |
| medium | [CVE-2026-78660](https://avd.aquasec.com/nvd/cve-2026-78660) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | net/http: golang: golang.org/x/net/http2: net/http: HTTP response smuggling via malformed HTTP/2 framing headers |
| medium | [CVE-2026-78663](https://avd.aquasec.com/nvd/cve-2026-78663) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | net/http: net/http/internal/http2: golang: golang.org/x/net/http2: golang: flow control limit bypass via double refund … |
| medium | [CVE-2026-94439](https://avd.aquasec.com/nvd/cve-2026-94439) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | net/http: golang: net/http: HTTP request smuggling via improper handling of HTTP/1 CONNECT responses |
| medium | [CVE-2026-94440](https://avd.aquasec.com/nvd/cve-2026-94440) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | net/textproto: mime/multipart: golang: golang: Denial of Service via memory limit bypass in multipart form parsing |
| medium | [CVE-2026-94448](https://avd.aquasec.com/nvd/cve-2026-94448) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | html/template: golang: golang: cross-site scripting via consecutive expressions in JavaScript template literals |
| medium | [CVE-2026-97030](https://avd.aquasec.com/nvd/cve-2026-97030) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | html/template: golang: html/template: Cross-site scripting via improper escaping of yield keyword |
| medium | [CVE-2026-97032](https://avd.aquasec.com/nvd/cve-2026-97032) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | net/http: net/http/internal/http2: golang: golang.org/x/net/http2: net/http: Denial of Service via concurrent HPACK enc… |

<details><summary>72 low or unknown</summary>

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| low | [CVE-2026-107928](https://avd.aquasec.com/nvd/cve-2026-107928) | curl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: info leak uploading over WebSockets with GnuTLS |
| low | [CVE-2026-107929](https://avd.aquasec.com/nvd/cve-2026-107929) | curl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: local interface connection reuse mixup |
| low | [CVE-2026-86409](https://avd.aquasec.com/nvd/cve-2026-86409) | curl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: stale SigV4 headers sent unintended |
| low | [CVE-2026-86410](https://avd.aquasec.com/nvd/cve-2026-86410) | curl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: redirect to SFTP or SCP bypasses host verification |
| low | [CVE-2026-86411](https://avd.aquasec.com/nvd/cve-2026-86411) | curl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: FTP connection reuse inherits client-certificate |
| low | [CVE-2026-86412](https://avd.aquasec.com/nvd/cve-2026-86412) | curl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: server-set cookie erases cookie jar |
| low | [CVE-2026-86413](https://avd.aquasec.com/nvd/cve-2026-86413) | curl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: Negotiate credentials sent to redirected origin |
| low | [CVE-2026-86414](https://avd.aquasec.com/nvd/cve-2026-86414) | curl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: leaking host credentials to HTTP proxy |
| low | [CVE-2026-86415](https://avd.aquasec.com/nvd/cve-2026-86415) | curl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: auto client cert connection reuse mixup |
| low | [CVE-2026-87861](https://avd.aquasec.com/nvd/cve-2026-87861) | curl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: cross-origin reuse of a Negotiate connection |
| low | [CVE-2026-87862](https://avd.aquasec.com/nvd/cve-2026-87862) | curl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: HSTS cache import drops longer-lived child |
| low | [CVE-2026-87863](https://avd.aquasec.com/nvd/cve-2026-87863) | curl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: Hsts Response Erases Persisted Entries |
| low | [CVE-2026-87864](https://avd.aquasec.com/nvd/cve-2026-87864) | curl | 8.22.0-r0 | 8.23.0-r0 | curl: IMAP APPEND plus CRLF conversion command injection |
| low | [CVE-2026-87865](https://avd.aquasec.com/nvd/cve-2026-87865) | curl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: knownhost callback mismatches key as missing |
| low | [CVE-2026-87866](https://avd.aquasec.com/nvd/cve-2026-87866) | curl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: wolfSSL public-key pinning bypass |
| low | [CVE-2026-92386](https://avd.aquasec.com/nvd/cve-2026-92386) | curl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: server-set cookie aborts cookie jar load |
| low | [CVE-2026-92387](https://avd.aquasec.com/nvd/cve-2026-92387) | curl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: never-ending loop via server response omission |
| low | [CVE-2026-92389](https://avd.aquasec.com/nvd/cve-2026-92389) | curl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: TFTP direction confusion |
| low | [CVE-2026-92390](https://avd.aquasec.com/nvd/cve-2026-92390) | curl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: -J no-clobber guarantee bypassed with 103 early hints |
| low | [CVE-2026-92391](https://avd.aquasec.com/nvd/cve-2026-92391) | curl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: curl_mime_free stack overflow by recursion |
| low | [CVE-2026-94265](https://avd.aquasec.com/nvd/cve-2026-94265) | curl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: Authorization: Negotiate sent to foreign origin |
| low | [CVE-2026-94266](https://avd.aquasec.com/nvd/cve-2026-94266) | curl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: password leak in CURLINFO_EFFECTIVE_URL |
| low | [CVE-2026-107928](https://avd.aquasec.com/nvd/cve-2026-107928) | curl-dev | 8.22.0-r0 | 8.23.0-r0 | curl: curl: info leak uploading over WebSockets with GnuTLS |
| low | [CVE-2026-107929](https://avd.aquasec.com/nvd/cve-2026-107929) | curl-dev | 8.22.0-r0 | 8.23.0-r0 | curl: curl: local interface connection reuse mixup |
| low | [CVE-2026-86409](https://avd.aquasec.com/nvd/cve-2026-86409) | curl-dev | 8.22.0-r0 | 8.23.0-r0 | curl: curl: stale SigV4 headers sent unintended |
| low | [CVE-2026-86410](https://avd.aquasec.com/nvd/cve-2026-86410) | curl-dev | 8.22.0-r0 | 8.23.0-r0 | curl: curl: redirect to SFTP or SCP bypasses host verification |
| low | [CVE-2026-86411](https://avd.aquasec.com/nvd/cve-2026-86411) | curl-dev | 8.22.0-r0 | 8.23.0-r0 | curl: curl: FTP connection reuse inherits client-certificate |
| low | [CVE-2026-86412](https://avd.aquasec.com/nvd/cve-2026-86412) | curl-dev | 8.22.0-r0 | 8.23.0-r0 | curl: curl: server-set cookie erases cookie jar |
| low | [CVE-2026-86413](https://avd.aquasec.com/nvd/cve-2026-86413) | curl-dev | 8.22.0-r0 | 8.23.0-r0 | curl: curl: Negotiate credentials sent to redirected origin |
| low | [CVE-2026-86414](https://avd.aquasec.com/nvd/cve-2026-86414) | curl-dev | 8.22.0-r0 | 8.23.0-r0 | curl: curl: leaking host credentials to HTTP proxy |
| low | [CVE-2026-86415](https://avd.aquasec.com/nvd/cve-2026-86415) | curl-dev | 8.22.0-r0 | 8.23.0-r0 | curl: curl: auto client cert connection reuse mixup |
| low | [CVE-2026-87861](https://avd.aquasec.com/nvd/cve-2026-87861) | curl-dev | 8.22.0-r0 | 8.23.0-r0 | curl: curl: cross-origin reuse of a Negotiate connection |
| low | [CVE-2026-87862](https://avd.aquasec.com/nvd/cve-2026-87862) | curl-dev | 8.22.0-r0 | 8.23.0-r0 | curl: curl: HSTS cache import drops longer-lived child |
| low | [CVE-2026-87863](https://avd.aquasec.com/nvd/cve-2026-87863) | curl-dev | 8.22.0-r0 | 8.23.0-r0 | curl: curl: Hsts Response Erases Persisted Entries |
| low | [CVE-2026-87864](https://avd.aquasec.com/nvd/cve-2026-87864) | curl-dev | 8.22.0-r0 | 8.23.0-r0 | curl: IMAP APPEND plus CRLF conversion command injection |
| low | [CVE-2026-87865](https://avd.aquasec.com/nvd/cve-2026-87865) | curl-dev | 8.22.0-r0 | 8.23.0-r0 | curl: curl: knownhost callback mismatches key as missing |
| low | [CVE-2026-87866](https://avd.aquasec.com/nvd/cve-2026-87866) | curl-dev | 8.22.0-r0 | 8.23.0-r0 | curl: curl: wolfSSL public-key pinning bypass |
| low | [CVE-2026-92386](https://avd.aquasec.com/nvd/cve-2026-92386) | curl-dev | 8.22.0-r0 | 8.23.0-r0 | curl: curl: server-set cookie aborts cookie jar load |
| low | [CVE-2026-92387](https://avd.aquasec.com/nvd/cve-2026-92387) | curl-dev | 8.22.0-r0 | 8.23.0-r0 | curl: curl: never-ending loop via server response omission |
| low | [CVE-2026-92389](https://avd.aquasec.com/nvd/cve-2026-92389) | curl-dev | 8.22.0-r0 | 8.23.0-r0 | curl: curl: TFTP direction confusion |
| low | [CVE-2026-92390](https://avd.aquasec.com/nvd/cve-2026-92390) | curl-dev | 8.22.0-r0 | 8.23.0-r0 | curl: curl: -J no-clobber guarantee bypassed with 103 early hints |
| low | [CVE-2026-92391](https://avd.aquasec.com/nvd/cve-2026-92391) | curl-dev | 8.22.0-r0 | 8.23.0-r0 | curl: curl: curl_mime_free stack overflow by recursion |
| low | [CVE-2026-94265](https://avd.aquasec.com/nvd/cve-2026-94265) | curl-dev | 8.22.0-r0 | 8.23.0-r0 | curl: curl: Authorization: Negotiate sent to foreign origin |
| low | [CVE-2026-94266](https://avd.aquasec.com/nvd/cve-2026-94266) | curl-dev | 8.22.0-r0 | 8.23.0-r0 | curl: curl: password leak in CURLINFO_EFFECTIVE_URL |
| low | [CVE-2026-81870](https://avd.aquasec.com/nvd/cve-2026-81870) | go.opentelemetry.io/otel/exporters/otlp/otlptrace | v1.44.0 | 1.45.0 | github.com/open-telemetry/opentelemetry-go: OpenTelemetry-Go: Information disclosure via exporter configuration logging |
| low | [CVE-2026-81870](https://avd.aquasec.com/nvd/cve-2026-81870) | go.opentelemetry.io/otel/exporters/otlp/otlptrace/otlptracegrpc | v1.44.0 | 1.45.0 | github.com/open-telemetry/opentelemetry-go: OpenTelemetry-Go: Information disclosure via exporter configuration logging |
| low | [CVE-2026-81870](https://avd.aquasec.com/nvd/cve-2026-81870) | go.opentelemetry.io/otel/sdk | v1.44.0 | 1.45.0 | github.com/open-telemetry/opentelemetry-go: OpenTelemetry-Go: Information disclosure via exporter configuration logging |
| low | [CVE-2026-107928](https://avd.aquasec.com/nvd/cve-2026-107928) | libcurl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: info leak uploading over WebSockets with GnuTLS |
| low | [CVE-2026-107929](https://avd.aquasec.com/nvd/cve-2026-107929) | libcurl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: local interface connection reuse mixup |
| low | [CVE-2026-86409](https://avd.aquasec.com/nvd/cve-2026-86409) | libcurl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: stale SigV4 headers sent unintended |
| low | [CVE-2026-86410](https://avd.aquasec.com/nvd/cve-2026-86410) | libcurl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: redirect to SFTP or SCP bypasses host verification |
| low | [CVE-2026-86411](https://avd.aquasec.com/nvd/cve-2026-86411) | libcurl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: FTP connection reuse inherits client-certificate |
| low | [CVE-2026-86412](https://avd.aquasec.com/nvd/cve-2026-86412) | libcurl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: server-set cookie erases cookie jar |
| low | [CVE-2026-86413](https://avd.aquasec.com/nvd/cve-2026-86413) | libcurl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: Negotiate credentials sent to redirected origin |
| low | [CVE-2026-86414](https://avd.aquasec.com/nvd/cve-2026-86414) | libcurl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: leaking host credentials to HTTP proxy |
| low | [CVE-2026-86415](https://avd.aquasec.com/nvd/cve-2026-86415) | libcurl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: auto client cert connection reuse mixup |
| low | [CVE-2026-87861](https://avd.aquasec.com/nvd/cve-2026-87861) | libcurl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: cross-origin reuse of a Negotiate connection |
| low | [CVE-2026-87862](https://avd.aquasec.com/nvd/cve-2026-87862) | libcurl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: HSTS cache import drops longer-lived child |
| low | [CVE-2026-87863](https://avd.aquasec.com/nvd/cve-2026-87863) | libcurl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: Hsts Response Erases Persisted Entries |
| low | [CVE-2026-87864](https://avd.aquasec.com/nvd/cve-2026-87864) | libcurl | 8.22.0-r0 | 8.23.0-r0 | curl: IMAP APPEND plus CRLF conversion command injection |
| low | [CVE-2026-87865](https://avd.aquasec.com/nvd/cve-2026-87865) | libcurl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: knownhost callback mismatches key as missing |
| low | [CVE-2026-87866](https://avd.aquasec.com/nvd/cve-2026-87866) | libcurl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: wolfSSL public-key pinning bypass |
| low | [CVE-2026-92386](https://avd.aquasec.com/nvd/cve-2026-92386) | libcurl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: server-set cookie aborts cookie jar load |
| low | [CVE-2026-92387](https://avd.aquasec.com/nvd/cve-2026-92387) | libcurl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: never-ending loop via server response omission |
| low | [CVE-2026-92389](https://avd.aquasec.com/nvd/cve-2026-92389) | libcurl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: TFTP direction confusion |
| low | [CVE-2026-92390](https://avd.aquasec.com/nvd/cve-2026-92390) | libcurl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: -J no-clobber guarantee bypassed with 103 early hints |
| low | [CVE-2026-92391](https://avd.aquasec.com/nvd/cve-2026-92391) | libcurl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: curl_mime_free stack overflow by recursion |
| low | [CVE-2026-94265](https://avd.aquasec.com/nvd/cve-2026-94265) | libcurl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: Authorization: Negotiate sent to foreign origin |
| low | [CVE-2026-94266](https://avd.aquasec.com/nvd/cve-2026-94266) | libcurl | 8.22.0-r0 | 8.23.0-r0 | curl: curl: password leak in CURLINFO_EFFECTIVE_URL |
| low | [CVE-2026-56857](https://avd.aquasec.com/nvd/cve-2026-56857) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | os: golang: golang: Directory traversal via Windows junction handling |
| unknown | GO-2026-5932 | golang.org/x/crypto | v0.55.0 |  | The golang.org/x/crypto/openpgp package is unmaintained, unsafe by design, and has known security issues |
| unknown | CVE-2026-107891 | musl-utils | 1.2.6-r2 | 1.2.6-r3 |  |

</details>
