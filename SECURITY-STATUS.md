# Security status

Known vulnerabilities in **main**: its lock files, and the `:dev` image built from it,
found by `composer audit`, `npm audit` and [Trivy](https://trivy.dev). A release carries
this file as it was when it was tagged. Written every week by
[a scheduled workflow](.github/workflows/security-status.yml) - do not edit by hand.

Updated: 2026-11-02 13:03 UTC

Main at: `40b8536`

| Source | Critical | High | Medium | Low | Unknown |
|--------|---------:|-----:|-------:|----:|--------:|
| PHP · ci4/composer.lock | 0 | 0 | 0 | 0 | 0 |
| JavaScript · vue/package-lock.json | 1 | 5 | 8 | 6 | 0 |
| Image · 4spaces/kubernetes-service-orchestrator:dev | 0 | 9 | 21 | 4 | 3 |

## PHP · ci4/composer.lock

*abandoned: vierbergenlars/php-semver*

Nothing known.

## JavaScript · vue/package-lock.json

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| critical | [GHSA-pqg4-j6r4-53mv](https://github.com/advisories/GHSA-pqg4-j6r4-53mv) | shell-quote | 1.10.0 | not >=1.8.4 <1.11.0 | shell-quote: `quote()` command injection via a line terminator in a token after a `{ comment }` token |
| high | [GHSA-8r9p-f939-6h3c](https://github.com/advisories/GHSA-8r9p-f939-6h3c) | follow-redirects | 1.16.0 | not >=1.16.0 <1.16.1 | follow-redirects sensitiveHeaders regex escaping can expose credential headers |
| high | [GHSA-mrwp-f3q8-3g9p](https://github.com/advisories/GHSA-mrwp-f3q8-3g9p) | follow-redirects | 1.16.0 | not >=1.16.0 <=1.16.1 | follow-redirects: Cross-realm sensitiveHeaders arrays are discarded and custom credentials can follow redirects |
| high | [GHSA-q3jh-wrc6-jgq8](https://github.com/advisories/GHSA-q3jh-wrc6-jgq8) | follow-redirects | 1.16.0 | not >=1.16.0 <1.16.1 | follow-redirects array-form headers bypass cross-origin credential stripping |
| high | [GHSA-68fv-2mgg-jv7q](https://github.com/advisories/GHSA-68fv-2mgg-jv7q) | source-map-js | 1.2.1 | not >=1.0.0 <1.2.2 | source-map-js allows event-loop denial of service through indexed source-map section offsets |
| high | [GHSA-587h-7wmj-h5pm](https://github.com/advisories/GHSA-587h-7wmj-h5pm) | vite | 8.3.0 | not >=8.3.0 <=8.3.2 | launch-editor: `fs.existsSync()` existence check can be bypassed on Windows |
| medium | [GHSA-w74x-v6f9-j3xg](https://github.com/advisories/GHSA-w74x-v6f9-j3xg) | fast-uri | 3.1.8 | not >=3.0.0 <3.1.9 | fast-uri vulnerable to URI scheme spoofing via percent-encoded scheme decoding |
| medium | [GHSA-wjr9-j795-jxcr](https://github.com/advisories/GHSA-wjr9-j795-jxcr) | fast-uri | 3.1.8 | not >=3.1.3 <3.1.9 | fast-uri vulnerable to host confusion via unchecked WHATWG host canonicalization |
| medium | [GHSA-3g3m-2fjr-6pw4](https://github.com/advisories/GHSA-3g3m-2fjr-6pw4) | glob | 11.1.0 | not >=9.0.0 <=14.0.1 | Glob: AbortSignal cancellation bypass allows post-timeout filesystem traversal |
| medium | [GHSA-g3hv-75wg-m249](https://github.com/advisories/GHSA-g3hv-75wg-m249) | minimatch |  | not <11.0.1 | minimatch has potential denial of service via globparts amplification |
| medium | [GHSA-698v-4r3x-3vcv](https://github.com/advisories/GHSA-698v-4r3x-3vcv) | vite | 8.3.0 | not >=8.3.0 <=8.3.2 | launch-editor: escapeCmdArgs() omits % escaping on Windows |
| medium | [GHSA-r825-w8mm-5pxw](https://github.com/advisories/GHSA-r825-w8mm-5pxw) | vite | 8.3.0 | not >=8.3.0 <=8.3.2 | launch-editor: Editor CLI flag misinterpretation causing arbitrary command execution for some editors |
| medium | [GHSA-rq7h-c2jc-7f22](https://github.com/advisories/GHSA-rq7h-c2jc-7f22) | vite | 8.3.0 | not >=8.3.0 <=8.3.2 | Vite: Files outside `server.fs.allow` could be served when project paths match system paths on non-Windows machine |
| medium | [GHSA-vfpm-58rq-9qcg](https://github.com/advisories/GHSA-vfpm-58rq-9qcg) | vite | 8.3.0 | not >=8.3.0 <=8.3.2 | Vite: First 4 bytes of files outside of `server.fs` setting is exposed by `?vite-wasm-instance` |

<details><summary>6 low or unknown</summary>

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| low | [GHSA-gcqx-w48m-w2h4](https://github.com/advisories/GHSA-gcqx-w48m-w2h4) | deepmerge | 4.3.1 | not >=4.2.0 <=4.3.1 | deepmerge mergeObject() allows prototype poisoning of returned objects |
| low | [GHSA-333x-w4r6-6crp](https://github.com/advisories/GHSA-333x-w4r6-6crp) | fast-uri | 3.1.8 | not >=3.0.0 <3.1.9 | fast-uri vulnerable to scheme injection via an unescaped first-segment colon in suffix references |
| low | [GHSA-xhq7-xmqj-769h](https://github.com/advisories/GHSA-xhq7-xmqj-769h) | fast-uri | 3.1.8 | not >=3.0.0 <3.1.9 | fast-uri vulnerable to improper path equivalence in relative reference normalization |
| low | [GHSA-gfhx-hw2g-v5hg](https://github.com/advisories/GHSA-gfhx-hw2g-v5hg) | serialize-javascript | 7.1.1 | not >=7.1.1 <7.1.2 | Serialize JavaScript: Cross-site scripting (XSS) via unescaped </script> in serialized function bodies |
| low | [GHSA-m5mm-p8h7-m29v](https://github.com/advisories/GHSA-m5mm-p8h7-m29v) | serialize-javascript | 7.1.1 | not <7.1.3 | Serialize JavaScript: Cross-site scripting (XSS) via unescaped </script at the end of a serialized function body |
| low | [GHSA-9jrq-w75r-8gcw](https://github.com/advisories/GHSA-9jrq-w75r-8gcw) | vite | 8.3.0 | not >=8.3.0 <=8.3.2 | Vite: Cross origin script execution through Vite custom middleware |

</details>

## Image · 4spaces/kubernetes-service-orchestrator:dev

*built from 40b8536, the commit above · alpine 3.24.3 · 4spaces/kubernetes-service-orchestrator@sha256:ed4ad334cd1fda277954a55d37eafb1b92ec7f186f32dc81a83e45f80a2e516a*

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| high | [CVE-2026-95837](https://avd.aquasec.com/nvd/cve-2026-95837) | github.com/containerd/containerd/v2 | v2.3.3 | 2.2.7, 2.3.4 | github.com/containerd/containerd: containerd: Privilege escalation via crafted container checkpoint restore |
| high | [CVE-2026-116587](https://avd.aquasec.com/nvd/cve-2026-116587) | github.com/go-jose/go-jose/v4 | v4.1.4 | 4.1.6 | github.com/go-jose/go-jose: go-jose: Denial of Service via crafted RSA parameters in JWKs |
| high | [CVE-2026-78669](https://avd.aquasec.com/nvd/cve-2026-78669) | golang.org/x/net | v0.58.0 | 0.60.0 | net/http: net/http/internal/http2: golang.org/x/net/http2: net/http: Denial of Service via excessive HTTP/2 SETTINGS fr… |
| high | [CVE-2026-84304](https://avd.aquasec.com/nvd/cve-2026-84304) | google.golang.org/grpc | v1.82.1 | 1.83.1 | google.golang.org/grpc: gRPC-Go: Denial of Service via HTTP/2 DATA Frame Fragmentation |
| high | [CVE-2026-84445](https://avd.aquasec.com/nvd/cve-2026-84445) | google.golang.org/grpc | v1.82.1 | 1.82.2, 1.83.2, 1.84.0-dev.0.20260825144003-d5a41119e0e3, 1.85.0-dev.0.20260825072537-93e31b48545e | google.golang.org/grpc: gRPC-Go: Denial of Service via malformed RPC requests |
| high | [CVE-2026-111943](https://avd.aquasec.com/nvd/cve-2026-111943) | stdlib | v1.26.6 | 1.26.10, 1.27.3 | crypto/x509: golang: crypto/x509: Man-in-the-middle attacks via untrusted intermediate certificates |
| high | [CVE-2026-78667](https://avd.aquasec.com/nvd/cve-2026-78667) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | net/http: golang: golang: Denial of Service via crafted HTTP Range headers |
| high | [CVE-2026-78669](https://avd.aquasec.com/nvd/cve-2026-78669) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | net/http: net/http/internal/http2: golang.org/x/net/http2: net/http: Denial of Service via excessive HTTP/2 SETTINGS fr… |
| high | [CVE-2026-97031](https://avd.aquasec.com/nvd/cve-2026-97031) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | crypto/tls: golang: crypto/tls: Denial of Service via multiple ECH outer extension references |
| medium | [CVE-2026-53493](https://avd.aquasec.com/nvd/cve-2026-53493) | github.com/containerd/containerd/v2 | v2.3.3 | 2.0.13, 2.2.9, 2.3.6, 2.4.1 | github.com/containerd/containerd: containerd: Denial of Service via crafted OCI image index |
| medium | [CVE-2026-53495](https://avd.aquasec.com/nvd/cve-2026-53495) | github.com/containerd/containerd/v2 | v2.3.3 | 2.0.12, 2.2.8, 2.3.5 | github.com/containerd/containerd: containerd: Denial of Service via CRI ExecSync goroutine leak |
| medium | [CVE-2026-95838](https://avd.aquasec.com/nvd/cve-2026-95838) | github.com/containerd/containerd/v2 | v2.3.3 | 2.0.12, 2.2.8, 2.3.5 | github.com/containerd/containerd: containerd: Denial of Service via repeated directory traversals during layer unpacking |
| medium | [CVE-2026-116585](https://avd.aquasec.com/nvd/cve-2026-116585) | github.com/go-jose/go-jose/v4 | v4.1.4 | 4.1.5 | github.com/go-jose/go-jose: go-jose: Denial of Service via empty ciphertext buffer during JWE decryption |
| medium | [CVE-2026-116586](https://avd.aquasec.com/nvd/cve-2026-116586) | github.com/go-jose/go-jose/v4 | v4.1.4 | 4.1.5 | github.com/go-jose/go-jose: go-jose: Denial of Service via deeply nested JSON parsing |
| medium | [CVE-2026-56855](https://avd.aquasec.com/nvd/cve-2026-56855) | golang.org/x/crypto | v0.55.0 | 0.56.0 | golang.org/x/crypto/ssh: golang.org/x/crypto/ssh: Denial of Service via crafted messages |
| medium | [CVE-2026-78662](https://avd.aquasec.com/nvd/cve-2026-78662) | golang.org/x/crypto | v0.55.0 | 0.56.0 | golang.org/x/crypto/ssh: golang.org/x/crypto/ssh: Denial of Service via channel request flooding |
| medium | [CVE-2026-78659](https://avd.aquasec.com/nvd/cve-2026-78659) | golang.org/x/net | v0.58.0 | 0.60.0 | net/http: golang.org/x/net/http2: golang: net/http: Denial of Service via HTTP/2 trailer header memory exhaustion |
| medium | [CVE-2026-78660](https://avd.aquasec.com/nvd/cve-2026-78660) | golang.org/x/net | v0.58.0 | 0.60.0 | net/http: golang: golang.org/x/net/http2: net/http: HTTP response smuggling via malformed HTTP/2 framing headers |
| medium | [CVE-2026-78663](https://avd.aquasec.com/nvd/cve-2026-78663) | golang.org/x/net | v0.58.0 | 0.60.0 | net/http: net/http/internal/http2: golang: golang.org/x/net/http2: golang: flow control limit bypass via double refund … |
| medium | [CVE-2026-97032](https://avd.aquasec.com/nvd/cve-2026-97032) | golang.org/x/net | v0.58.0 | 0.60.0 | net/http: net/http/internal/http2: golang: golang.org/x/net/http2: net/http: Denial of Service via concurrent HPACK enc… |
| medium | [CVE-2026-84303](https://avd.aquasec.com/nvd/cve-2026-84303) | google.golang.org/grpc | v1.82.1 | 1.83.1 | gRPC-Go is the Go language implementation of gRPC. Prior to 1.83.1, th ... |
| medium | [CVE-2026-56866](https://avd.aquasec.com/nvd/cve-2026-56866) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | net/http: golang: golang: Cross-user response poisoning via desynchronized HTTP CONNECT connections |
| medium | [CVE-2026-78659](https://avd.aquasec.com/nvd/cve-2026-78659) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | net/http: golang.org/x/net/http2: golang: net/http: Denial of Service via HTTP/2 trailer header memory exhaustion |
| medium | [CVE-2026-78660](https://avd.aquasec.com/nvd/cve-2026-78660) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | net/http: golang: golang.org/x/net/http2: net/http: HTTP response smuggling via malformed HTTP/2 framing headers |
| medium | [CVE-2026-78663](https://avd.aquasec.com/nvd/cve-2026-78663) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | net/http: net/http/internal/http2: golang: golang.org/x/net/http2: golang: flow control limit bypass via double refund … |
| medium | [CVE-2026-94439](https://avd.aquasec.com/nvd/cve-2026-94439) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | net/http: golang: net/http: HTTP request smuggling via improper handling of HTTP/1 CONNECT responses |
| medium | [CVE-2026-94440](https://avd.aquasec.com/nvd/cve-2026-94440) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | net/textproto: mime/multipart: golang: golang: Denial of Service via memory limit bypass in multipart form parsing |
| medium | [CVE-2026-94448](https://avd.aquasec.com/nvd/cve-2026-94448) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | html/template: golang: golang: cross-site scripting via consecutive expressions in JavaScript template literals |
| medium | [CVE-2026-97030](https://avd.aquasec.com/nvd/cve-2026-97030) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | html/template: golang: html/template: Cross-site scripting via improper escaping of yield keyword |
| medium | [CVE-2026-97032](https://avd.aquasec.com/nvd/cve-2026-97032) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | net/http: net/http/internal/http2: golang: golang.org/x/net/http2: net/http: Denial of Service via concurrent HPACK enc… |

<details><summary>7 low or unknown</summary>

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| low | [CVE-2026-81870](https://avd.aquasec.com/nvd/cve-2026-81870) | go.opentelemetry.io/otel/exporters/otlp/otlptrace | v1.44.0 | 1.45.0 | github.com/open-telemetry/opentelemetry-go: OpenTelemetry-Go: Information disclosure via exporter configuration logging |
| low | [CVE-2026-81870](https://avd.aquasec.com/nvd/cve-2026-81870) | go.opentelemetry.io/otel/exporters/otlp/otlptrace/otlptracegrpc | v1.44.0 | 1.45.0 | github.com/open-telemetry/opentelemetry-go: OpenTelemetry-Go: Information disclosure via exporter configuration logging |
| low | [CVE-2026-81870](https://avd.aquasec.com/nvd/cve-2026-81870) | go.opentelemetry.io/otel/sdk | v1.44.0 | 1.45.0 | github.com/open-telemetry/opentelemetry-go: OpenTelemetry-Go: Information disclosure via exporter configuration logging |
| low | [CVE-2026-56857](https://avd.aquasec.com/nvd/cve-2026-56857) | stdlib | v1.26.6 | 1.26.9, 1.27.2 | os: golang: golang: Directory traversal via Windows junction handling |
| unknown | GO-2026-5932 | golang.org/x/crypto | v0.55.0 |  | The golang.org/x/crypto/openpgp package is unmaintained, unsafe by design, and has known security issues |
| unknown | [CVE-2026-78661](https://avd.aquasec.com/nvd/cve-2026-78661) | golang.org/x/net | v0.58.0 | 0.62.0 | When coalescing adjacent text nodes in addText and fosterParent, appen ... |
| unknown | [CVE-2026-97045](https://avd.aquasec.com/nvd/cve-2026-97045) | golang.org/x/net | v0.58.0 | 0.62.0 | In childTextNodesAreLiteral, walking up the tree previously stopped at ... |

</details>
