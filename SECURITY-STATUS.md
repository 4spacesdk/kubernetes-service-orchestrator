# Security status

Known vulnerabilities in **main**: its lock files, and the `:dev` image built from it,
found by `composer audit`, `npm audit` and [Trivy](https://trivy.dev). A release carries
this file as it was when it was tagged. Written every week by
[a scheduled workflow](.github/workflows/security-status.yml) - do not edit by hand.

Updated: 2026-09-21 16:09 UTC

Main at: `e39a395`

| Source | Critical | High | Medium | Low | Unknown |
|--------|---------:|-----:|-------:|----:|--------:|
| PHP · ci4/composer.lock | 0 | 0 | 0 | 0 | 0 |
| PHP · zmq-client/composer.lock | 0 | 1 | 6 | 0 | 0 |
| PHP · zmq-server/composer.lock | 0 | 1 | 7 | 0 | 0 |
| JavaScript · vue/package-lock.json | 1 | 40 | 36 | 4 | 0 |
| Image · 4spaces/kubernetes-service-orchestrator:dev | 1 | 2 | 12 | 0 | 0 |

## PHP · ci4/composer.lock

*abandoned: swiftmailer/swiftmailer, vierbergenlars/php-semver*

Nothing known.

## PHP · zmq-client/composer.lock

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| high | [CVE-2024-51736](https://symfony.com/cve-2024-51736) | symfony/process | v5.4.26 | not >=5.4.0,<5.4.46 | CVE-2024-51736: Command execution hijack on Windows with Process class |
| medium | [CVE-2026-48998](https://github.com/guzzle/psr7/security/advisories/GHSA-34xg-wgjx-8xph) | guzzlehttp/psr7 | 2.6.0 | not <2.10.2 | Host confusion via authority reinterpretation |
| medium | [CVE-2026-49214](https://github.com/guzzle/psr7/security/advisories/GHSA-hq7v-mx3g-29hw) | guzzlehttp/psr7 | 2.6.0 | not <2.10.2 | CRLF injection via URI host component |
| medium | [CVE-2026-55766](https://github.com/guzzle/psr7/security/advisories/GHSA-vm85-hxw5-5432) | guzzlehttp/psr7 | 2.6.0 | not <2.12.1 | CRLF injection in HTTP start-line serialization |
| medium | [CVE-2026-59882](https://github.com/advisories/GHSA-c2w2-prh8-qm98) | guzzlehttp/psr7 | 2.6.0 | not <2.12.3 | guzzlehttp/psr7: Host Confusion via Weak URI Host Validation |
| medium | [CVE-2025-22145](https://github.com/advisories/GHSA-j3f9-p6hm-5w6q) | nesbot/carbon | 2.69.0 | not <2.72.6 | Carbon has an arbitrary file include via unvalidated input passed to Carbon::setLocale |
| medium | [CVE-2026-24739](https://github.com/advisories/GHSA-r39x-jcww-82v6) | symfony/process | v5.4.26 | not <5.4.51 | Symfony's incorrect argument escaping under MSYS2/Git Bash can lead to destructive file operations on Windows |

## PHP · zmq-server/composer.lock

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| high | [CVE-2025-64500](https://symfony.com/blog/cve-2025-64500-incorrect-parsing-of-path-info-can-lead-to-limited-authorizati… | symfony/http-foundation | v6.4.18 | not >=6.4.0,<6.4.29 | CVE-2025-64500: Incorrect parsing of PATH_INFO can lead to limited authorization bypass |
| medium | [CVE-2026-48998](https://github.com/guzzle/psr7/security/advisories/GHSA-34xg-wgjx-8xph) | guzzlehttp/psr7 | 1.9.1 | not <2.10.2 | Host confusion via authority reinterpretation |
| medium | [CVE-2026-49214](https://github.com/guzzle/psr7/security/advisories/GHSA-hq7v-mx3g-29hw) | guzzlehttp/psr7 | 1.9.1 | not <2.10.2 | CRLF injection via URI host component |
| medium | [CVE-2026-55766](https://github.com/guzzle/psr7/security/advisories/GHSA-vm85-hxw5-5432) | guzzlehttp/psr7 | 1.9.1 | not <2.12.1 | CRLF injection in HTTP start-line serialization |
| medium | [CVE-2026-59882](https://github.com/advisories/GHSA-c2w2-prh8-qm98) | guzzlehttp/psr7 | 1.9.1 | not <2.12.3 | guzzlehttp/psr7: Host Confusion via Weak URI Host Validation |
| medium | [CVE-2026-48736](https://symfony.com/cve-2026-48736) | symfony/http-foundation | v6.4.18 | not >=6.4.0,<6.4.41 | CVE-2026-48736: IpUtils::PRIVATE_SUBNETS Omits IPv6 Transition Forms (6to4, NAT64, Teredo, IPv4-compatible): SSRF Bypas… |
| medium | [CVE-2026-45065](https://symfony.com/cve-2026-45065) | symfony/routing | v6.4.18 | not >=6.4.0,<6.4.40 | CVE-2026-45065: UrlGenerator Route-Requirement Bypass via Unanchored Regex Alternation → Off-Site //host URL Injection |
| medium | [CVE-2026-48784](https://symfony.com/cve-2026-48784) | symfony/routing | v6.4.18 | not >=6.4.0,<6.4.41 | CVE-2026-48784: UrlGenerator Dot-Segment Encoding Skips Every Other Chained `../` or `./` → Generated URL Collapses Off… |

## JavaScript · vue/package-lock.json

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| critical | [GHSA-w7jw-789q-3m8p](https://github.com/advisories/GHSA-w7jw-789q-3m8p) | shell-quote | 1.8.1 | not >=1.1.0 <=1.8.3 | shell-quote quote() does not escape newlines in object .op values |
| high | [GHSA-fv7c-fp4j-7gwp](https://github.com/advisories/GHSA-fv7c-fp4j-7gwp) | @babel/plugin-transform-modules-systemjs | 7.25.0 | not >=7.12.0 <=7.29.3 | @babel/plugin-transform-modules-systemjs generates arbitrary code when compiling malicious input |
| high | [GHSA-3g43-6gmg-66jw](https://github.com/advisories/GHSA-3g43-6gmg-66jw) | axios | 0.19.2 | not >=0.19.0 <0.31.1 | axios Vulnerable to Credential Theft and Response Hijacking via Prototype Pollution Gadget in Config Merge |
| high | [GHSA-43fc-jf86-j433](https://github.com/advisories/GHSA-43fc-jf86-j433) | axios | 0.19.2 | not <=0.30.2 | Axios is Vulnerable to Denial of Service via __proto__ Key in mergeConfig |
| high | [GHSA-cph5-m8f7-6c5x](https://github.com/advisories/GHSA-cph5-m8f7-6c5x) | axios | 0.19.2 | not <0.21.2 | axios Inefficient Regular Expression Complexity vulnerability |
| high | [GHSA-hfxv-24rg-xrqf](https://github.com/advisories/GHSA-hfxv-24rg-xrqf) | axios | 0.19.2 | not <=0.31.1 | Axios: Regular Expression Denial of Service (ReDoS) via Cookie Name Injection |
| high | [GHSA-jr5f-v2jv-69x6](https://github.com/advisories/GHSA-jr5f-v2jv-69x6) | axios | 0.19.2 | not <0.30.0 | axios Requests Vulnerable To Possible SSRF and Credential Leakage via Absolute URL |
| high | [GHSA-3jxr-9vmj-r5cp](https://github.com/advisories/GHSA-3jxr-9vmj-r5cp) | brace-expansion | 1.1.11 | not <1.1.16 | brace-expansion: DoS via exponential-time expansion of consecutive non-expanding {} groups |
| high | [GHSA-mh99-v99m-4gvg](https://github.com/advisories/GHSA-mh99-v99m-4gvg) | brace-expansion | 1.1.11 | not >=2.0.0 <2.1.3 | brace-expansion: DoS via unbounded expansion length causing an out-of-memory process crash |
| high | [GHSA-rgw5-rvv9-x895](https://github.com/advisories/GHSA-rgw5-rvv9-x895) | brace-expansion | 1.1.11 | not <1.1.18 | brace-expansion: DoS via unbounded intermediate arrays, bypassing the CVE-2026-14257 mitigation |
| high | [GHSA-c83g-rgw3-j3cx](https://github.com/advisories/GHSA-c83g-rgw3-j3cx) | browserslist | 4.23.2 | not <=4.28.6 | Browserslist: Unbounded memory growth (no cache eviction) via distinct query results, leading to eventual OOM |
| high | [GHSA-3xgq-45jj-v275](https://github.com/advisories/GHSA-3xgq-45jj-v275) | cross-spawn | 7.0.3 | not >=7.0.0 <7.0.5 | Regular Expression Denial of Service (ReDoS) in cross-spawn |
| high | [GHSA-4c8g-83qw-93j6](https://github.com/advisories/GHSA-4c8g-83qw-93j6) | fast-uri | 3.0.1 | not >=3.0.0 <3.1.3 | fast-uri vulnerable to host confusion via failed IDN canonicalization |
| high | [GHSA-7p8r-x3mc-p8w7](https://github.com/advisories/GHSA-7p8r-x3mc-p8w7) | fast-uri | 3.0.1 | not >=3.0.0 <3.1.5 | fast-uri vulnerable to host confusion via backslash authority introducer |
| high | [GHSA-f65p-4m7j-42xc](https://github.com/advisories/GHSA-f65p-4m7j-42xc) | fast-uri | 3.0.1 | not >=3.0.0 <3.1.6 | fast-uri vulnerable to server-side request forgery via malformed IPv6 normalization |
| high | [GHSA-q3j6-qgpj-74h6](https://github.com/advisories/GHSA-q3j6-qgpj-74h6) | fast-uri | 3.0.1 | not >=3.0.0 <=3.1.0 | fast-uri vulnerable to path traversal via percent-encoded dot segments |
| high | [GHSA-v2hh-gcrm-f6hx](https://github.com/advisories/GHSA-v2hh-gcrm-f6hx) | fast-uri | 3.0.1 | not >=3.0.0 <=3.1.3 | fast-uri vulnerable to host confusion via literal backslash authority delimiter |
| high | [GHSA-v39h-62p7-jpjc](https://github.com/advisories/GHSA-v39h-62p7-jpjc) | fast-uri | 3.0.1 | not >=3.0.0 <=3.1.1 | fast-uri vulnerable to host confusion via percent-encoded authority delimiters |
| high | [GHSA-25h7-pfq9-p65f](https://github.com/advisories/GHSA-25h7-pfq9-p65f) | flatted | 3.3.1 | not <3.4.0 | flatted vulnerable to unbounded recursion DoS in parse() revive phase |
| high | [GHSA-rf6f-7fwh-wjgh](https://github.com/advisories/GHSA-rf6f-7fwh-wjgh) | flatted | 3.3.1 | not <=3.4.1 | Prototype Pollution via parse() in NodeJS flatted |
| high | [GHSA-74fj-2j2h-c42q](https://github.com/advisories/GHSA-74fj-2j2h-c42q) | follow-redirects | 1.5.10 | not <1.14.7 | Exposure of sensitive information in follow-redirects |
| high | [GHSA-v56q-mh7h-f735](https://github.com/advisories/GHSA-v56q-mh7h-f735) | immutable | 4.3.7 | not >=4.0.0-rc.1 <4.3.9 | Immutable.js `List` 32-bit trie overflow → unrecoverable DoS |
| high | [GHSA-wf6x-7x77-mvgw](https://github.com/advisories/GHSA-wf6x-7x77-mvgw) | immutable | 4.3.7 | not >=4.0.0-rc.1 <4.3.8 | Immutable is vulnerable to Prototype Pollution |
| high | [GHSA-xvcm-6775-5m9r](https://github.com/advisories/GHSA-xvcm-6775-5m9r) | immutable | 4.3.7 | not >=4.0.0-beta.1 <4.3.9 | Immutable: Hash-collision algorithmic complexity denial of service in Immutable.Map/Set |
| high | [GHSA-2883-xcg3-v3hh](https://github.com/advisories/GHSA-2883-xcg3-v3hh) | js-yaml | 4.1.0 | not >=4.0.0 <4.3.2 | js-yaml: maxTotalMergeKeys does not limit CPU use for empty merge sources |
| high | [GHSA-52cp-r559-cp3m](https://github.com/advisories/GHSA-52cp-r559-cp3m) | js-yaml | 4.1.0 | not >=4.0.0 <4.3.0 | js-yaml: YAML merge-key chains can force quadratic CPU consumption |
| high | [GHSA-5p4m-2wfm-xmqj](https://github.com/advisories/GHSA-5p4m-2wfm-xmqj) | js-yaml | 4.1.0 | not >=4.0.0 <4.3.1 | JS-YAML: Quadratic CPU consumption in !!omap resolution (3.x and 4.x) — CVE-2026-59870 fix not backported |
| high | [GHSA-r5fr-rjxr-66jc](https://github.com/advisories/GHSA-r5fr-rjxr-66jc) | lodash | 4.17.21 | not >=4.0.0 <=4.17.23 | lodash vulnerable to Code Injection via `_.template` imports key names |
| high | [GHSA-23c5-xmqv-rm74](https://github.com/advisories/GHSA-23c5-xmqv-rm74) | minimatch | 3.1.2 | not <3.1.4 | minimatch ReDoS: nested *() extglobs generate catastrophically backtracking regular expressions |
| high | [GHSA-3ppc-4f35-3m26](https://github.com/advisories/GHSA-3ppc-4f35-3m26) | minimatch | 3.1.2 | not >=9.0.0 <9.0.6 | minimatch has a ReDoS via repeated wildcards with non-matching literal in pattern |
| high | [GHSA-7r86-cg39-jmmj](https://github.com/advisories/GHSA-7r86-cg39-jmmj) | minimatch | 3.1.2 | not >=9.0.0 <9.0.7 | minimatch has ReDoS: matchOne() combinatorial backtracking via multiple non-adjacent GLOBSTAR segments |
| high | [GHSA-28wg-ghj8-5hjv](https://github.com/advisories/GHSA-28wg-ghj8-5hjv) | nanoid | 3.3.7 | not <3.3.16 | nanoid: non-secure generators can loop indefinitely with negative size |
| high | [GHSA-2v37-7h3g-55p8](https://github.com/advisories/GHSA-2v37-7h3g-55p8) | nanoid | 3.3.7 | not <3.3.18 | nanoid: custom generators can loop indefinitely when size is zero |
| high | [GHSA-xwg4-73v4-xw9w](https://github.com/advisories/GHSA-xwg4-73v4-xw9w) | nanoid | 3.3.7 | not <3.3.12 | nanoid: Integer Overflow or Wraparound |
| high | [GHSA-6g55-p6wh-862q](https://github.com/advisories/GHSA-6g55-p6wh-862q) | postcss | 8.4.40 | not <=8.5.11 | PostCSS: Arbitrary file read and information disclosure via attacker-controlled sourceMappingURL in CSS comments |
| high | [GHSA-r28c-9q8g-f849](https://github.com/advisories/GHSA-r28c-9q8g-f849) | postcss | 8.4.40 | not <=8.5.17 | PostCSS: Path Traversal in Previous Source Map Auto-Loading (sourceMappingURL) leads to Arbitrary .map File Disclosure |
| high | [GHSA-gcx4-mw62-g8wm](https://github.com/advisories/GHSA-gcx4-mw62-g8wm) | rollup | 3.29.4 | not >=3.0.0 <3.29.5 | DOM Clobbering Gadget found in rollup bundled scripts that leads to XSS |
| high | [GHSA-mw96-cpmx-2vgc](https://github.com/advisories/GHSA-mw96-cpmx-2vgc) | rollup | 3.29.4 | not <2.80.0 | Rollup 4 has Arbitrary File Write via Path Traversal |
| high | [GHSA-5c6j-r48x-rmvq](https://github.com/advisories/GHSA-5c6j-r48x-rmvq) | serialize-javascript | 4.0.0 | not <=7.0.2 | Serialize JavaScript is Vulnerable to RCE via RegExp.flags and Date.prototype.toISOString() |
| high | [GHSA-395f-4hp3-45gv](https://github.com/advisories/GHSA-395f-4hp3-45gv) | shell-quote | 1.8.1 | not <=1.8.4 | shell-quote: Quadratic-complexity Denial of Service in `parse()` (CWE-407) |
| high | [GHSA-c27g-q93r-2cwf](https://github.com/advisories/GHSA-c27g-q93r-2cwf) | vite | 4.5.3 | not <=5.4.8 | launch-editor vulnerable to command injection via the crafted request on Windows |
| medium | [GHSA-968p-4wvh-cqc8](https://github.com/advisories/GHSA-968p-4wvh-cqc8) | @babel/helpers | 7.25.0 | not <7.26.10 | Babel has inefficient RegExp complexity in generated code with .replace when transpiling named capturing groups |
| medium | [GHSA-968p-4wvh-cqc8](https://github.com/advisories/GHSA-968p-4wvh-cqc8) | @babel/runtime | 7.25.0 | not <7.26.10 | Babel has inefficient RegExp complexity in generated code with .replace when transpiling named capturing groups |
| medium | [GHSA-2g4f-4pwh-qvx6](https://github.com/advisories/GHSA-2g4f-4pwh-qvx6) | ajv | 6.12.6 | not >=7.0.0-alpha.0 <8.18.0 | ajv has ReDoS when using `$data` option |
| medium | [GHSA-4w2v-q235-vp99](https://github.com/advisories/GHSA-4w2v-q235-vp99) | axios | 0.19.2 | not <0.21.1 | Axios vulnerable to Server-Side Request Forgery |
| medium | [GHSA-62hf-57xw-28j9](https://github.com/advisories/GHSA-62hf-57xw-28j9) | axios | 0.19.2 | not <=0.31.0 | Axios: unbounded recursion in toFormData causes DoS via deeply nested request data |
| medium | [GHSA-7q8q-rj6j-mhjq](https://github.com/advisories/GHSA-7q8q-rj6j-mhjq) | axios | 0.19.2 | not >=0.8.0 <0.33.0 | Axios: Nested axios option objects can consume polluted prototype values |
| medium | [GHSA-fvcv-3m26-pcqx](https://github.com/advisories/GHSA-fvcv-3m26-pcqx) | axios | 0.19.2 | not <0.31.0 | Axios has Unrestricted Cloud Metadata Exfiltration via Header Injection Chain |
| medium | [GHSA-mmx7-hfxf-jppx](https://github.com/advisories/GHSA-mmx7-hfxf-jppx) | axios | 0.19.2 | not <0.33.0 | Axios: Prototype pollution gadgets can alter axios request construction |
| medium | [GHSA-wf5p-g6vw-rhxx](https://github.com/advisories/GHSA-wf5p-g6vw-rhxx) | axios | 0.19.2 | not >=0.8.1 <0.28.0 | Axios Cross-Site Request Forgery Vulnerability |
| medium | [GHSA-f886-m6hf-6m8v](https://github.com/advisories/GHSA-f886-m6hf-6m8v) | brace-expansion | 1.1.11 | not >=2.0.0 <2.0.3 | brace-expansion: Zero-step sequence causes process hang and memory exhaustion |
| medium | [GHSA-67mh-4wv8-2f99](https://github.com/advisories/GHSA-67mh-4wv8-2f99) | esbuild | 0.18.20 | not <=0.24.2 | esbuild enables any website to send any requests to the development server and read the response |
| medium | [GHSA-cxjh-pqwp-8mfp](https://github.com/advisories/GHSA-cxjh-pqwp-8mfp) | follow-redirects | 1.5.10 | not <=1.15.5 | follow-redirects' Proxy-Authorization header kept across hosts |
| medium | [GHSA-jchw-25xp-jwwc](https://github.com/advisories/GHSA-jchw-25xp-jwwc) | follow-redirects | 1.5.10 | not <1.15.4 | Follow Redirects improperly handles URLs in the url.parse() function |
| medium | [GHSA-pw2r-vq6v-hr8c](https://github.com/advisories/GHSA-pw2r-vq6v-hr8c) | follow-redirects | 1.5.10 | not <1.14.8 | Exposure of Sensitive Information to an Unauthorized Actor in follow-redirects |
| medium | [GHSA-r4q5-vmmm-2653](https://github.com/advisories/GHSA-r4q5-vmmm-2653) | follow-redirects | 1.5.10 | not <=1.15.11 | follow-redirects leaks Custom Authentication Headers to Cross-Domain Redirect Targets |
| medium | [GHSA-h67p-54hq-rp68](https://github.com/advisories/GHSA-h67p-54hq-rp68) | js-yaml | 4.1.0 | not >=4.0.0 <=4.1.1 | JS-YAML: Quadratic-complexity DoS in merge key handling via repeated aliases |
| medium | [GHSA-mh29-5h37-fv8m](https://github.com/advisories/GHSA-mh29-5h37-fv8m) | js-yaml | 4.1.0 | not >=4.0.0 <4.1.1 | js-yaml has prototype pollution in merge (<<) |
| medium | [GHSA-f23m-r3pf-42rh](https://github.com/advisories/GHSA-f23m-r3pf-42rh) | lodash | 4.17.21 | not <=4.17.23 | lodash vulnerable to Prototype Pollution via array path bypass in `_.unset` and `_.omit` |
| medium | [GHSA-xxjr-mmjv-4gpg](https://github.com/advisories/GHSA-xxjr-mmjv-4gpg) | lodash | 4.17.21 | not >=4.0.0 <=4.17.22 | Lodash has Prototype Pollution Vulnerability in `_.unset` and `_.omit` functions |
| medium | [GHSA-952p-6rrq-rcjv](https://github.com/advisories/GHSA-952p-6rrq-rcjv) | micromatch | 4.0.7 | not <4.0.8 | Regular Expression Denial of Service (ReDoS) in micromatch |
| medium | [GHSA-mwcw-c2x4-8c55](https://github.com/advisories/GHSA-mwcw-c2x4-8c55) | nanoid | 3.3.7 | not <3.3.8 | Predictable results in nanoid generation when given non-integer values |
| medium | [GHSA-3v7f-55p6-f55p](https://github.com/advisories/GHSA-3v7f-55p6-f55p) | picomatch | 2.3.1 | not <2.3.2 | Picomatch: Method Injection in POSIX Character Classes causes incorrect Glob Matching |
| medium | [GHSA-fxqj-rqcc-2cmp](https://github.com/advisories/GHSA-fxqj-rqcc-2cmp) | postcss | 8.4.40 | not <=8.5.22 | PostCSS: incomplete fix of GHSA-6g55-p6wh-862q — attacker-controlled sourceMappingURL reads arbitrary .map files when `… |
| medium | [GHSA-qx2v-qp2m-jg93](https://github.com/advisories/GHSA-qx2v-qp2m-jg93) | postcss | 8.4.40 | not <8.5.10 | PostCSS has XSS via Unescaped </style> in its CSS Stringify Output |
| medium | [GHSA-356w-63v5-8wf4](https://github.com/advisories/GHSA-356w-63v5-8wf4) | vite | 4.5.3 | not <4.5.13 | Vite has an `server.fs.deny` bypass with an invalid `request-target` |
| medium | [GHSA-4r4m-qw57-chr8](https://github.com/advisories/GHSA-4r4m-qw57-chr8) | vite | 4.5.3 | not <4.5.11 | Vite has a `server.fs.deny` bypassed for `inline` and `raw` with `?import` query |
| medium | [GHSA-4w7w-66w2-5vf9](https://github.com/advisories/GHSA-4w7w-66w2-5vf9) | vite | 4.5.3 | not <=6.4.1 | Vite Vulnerable to Path Traversal in Optimized Deps `.map` Handling |
| medium | [GHSA-64vr-g452-qvp3](https://github.com/advisories/GHSA-64vr-g452-qvp3) | vite | 4.5.3 | not >=4.0.0 <4.5.4 | Vite DOM Clobbering gadget found in vite bundled scripts that leads to XSS |
| medium | [GHSA-859w-5945-r5v3](https://github.com/advisories/GHSA-859w-5945-r5v3) | vite | 4.5.3 | not <=4.5.13 | Vite's server.fs.deny bypassed with /. for files under project root |
| medium | [GHSA-93m4-6634-74q7](https://github.com/advisories/GHSA-93m4-6634-74q7) | vite | 4.5.3 | not >=4.5.3 <5.0.0 | vite allows server.fs.deny bypass via backslash on Windows |
| medium | [GHSA-9cwx-2883-4wfx](https://github.com/advisories/GHSA-9cwx-2883-4wfx) | vite | 4.5.3 | not >=4.0.0 <=4.5.3 | Vite's `server.fs.deny` is bypassed when using `?import&raw` |
| medium | [GHSA-v6wh-96g9-6wx3](https://github.com/advisories/GHSA-v6wh-96g9-6wx3) | vite | 4.5.3 | not <=6.4.2 | launch-editor: NTLMv2 hash disclosure via UNC path handling on Windows |
| medium | [GHSA-vg6x-rcgg-rjx6](https://github.com/advisories/GHSA-vg6x-rcgg-rjx6) | vite | 4.5.3 | not <=4.5.5 | Websites were able to send any requests to the development server and read the response in vite |
| medium | [GHSA-x574-m823-4x7w](https://github.com/advisories/GHSA-x574-m823-4x7w) | vite | 4.5.3 | not <4.5.10 | Vite bypasses server.fs.deny when using ?raw?? |
| medium | [GHSA-xcj6-pq6g-qj4x](https://github.com/advisories/GHSA-xcj6-pq6g-qj4x) | vite | 4.5.3 | not <4.5.12 | Vite allows server.fs.deny to be bypassed with .svg or relative paths |
| medium | [GHSA-g3ch-rx76-35fx](https://github.com/advisories/GHSA-g3ch-rx76-35fx) | vue-template-compiler | 2.7.16 | not >=2.0.0 <3.0.0 | vue-template-compiler vulnerable to client-side Cross-Site Scripting (XSS) |

<details><summary>4 low or unknown</summary>

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| low | [GHSA-4x5r-pxfx-6jf8](https://github.com/advisories/GHSA-4x5r-pxfx-6jf8) | @babel/core | 7.24.9 | not <=7.29.0 | @babel/core: Arbitrary File Read via sourceMappingURL Comment |
| low | [GHSA-v6h2-p8h4-qcjw](https://github.com/advisories/GHSA-v6h2-p8h4-qcjw) | brace-expansion | 1.1.11 | not >=2.0.0 <=2.0.1 | brace-expansion Regular Expression Denial of Service vulnerability |
| low | [GHSA-w9m9-85wc-3x92](https://github.com/advisories/GHSA-w9m9-85wc-3x92) | postcss-selector-parser | 6.1.1 | not >=6.1.0 <6.1.3 | postcss-selector-parser allows denial of service through uncontrolled AST recursion |
| low | [GHSA-g4jq-h2w9-997c](https://github.com/advisories/GHSA-g4jq-h2w9-997c) | vite | 4.5.3 | not <=5.4.19 | Vite middleware may serve files starting with the same name with the public directory |

</details>

## Image · 4spaces/kubernetes-service-orchestrator:dev

*built from 63115db - behind main, whose latest push is not built yet · alpine 3.24.2 · 4spaces/kubernetes-service-orchestrator@sha256:44fb213a5893b2c15e749417209b2d7f17fb37c8b409901ca8d54f508bac145b*

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| critical | [CVE-2024-51736](https://avd.aquasec.com/nvd/cve-2024-51736) | symfony/process | v5.4.26 | 3.0.0, 6.3.0, 6.4.14, 7.1.0, 7.1.7, 5.0.0, 5.3.0, 5.4.0, 6.2.0, 6.4.0, 4.0.0, 5.4.46, 6.1.0, 5.1.0, 5.2.0 | CVE-2024-51736: Command execution hijack on Windows with Process class |
| high | [CVE-2025-64500](https://avd.aquasec.com/nvd/cve-2025-64500) | symfony/http-foundation | v6.4.18 | 6.4.0, 7.1.0, 5.0.0, 5.2.0, 5.3.0, 6.3.0, 7.3.7, 6.1.0, 6.4.29, 3.0.0, 4.0.0, 5.1.0, 6.2.0, 7.2.0, 7.3.0, 5.4.0, 5.4.50 | Symfony is a PHP framework for web and console applications and a set  ... |
| high | [CVE-2026-48736](https://avd.aquasec.com/nvd/cve-2026-48736) | symfony/http-foundation | v6.4.18 | 7.2.0, 7.3.0, 7.4.0, 7.4.13, 8.0.13, 6.4.41, 7.1.0 | Symfony is a PHP framework for web and console applications and a set  ... |
| medium | [CVE-2026-48998](https://avd.aquasec.com/nvd/cve-2026-48998) | guzzlehttp/psr7 | 2.6.0 | 2.10.2 | guzzlehttp/psr7: guzzlehttp/psr7: Information disclosure via improper Host header validation |
| medium | [CVE-2026-48998](https://avd.aquasec.com/nvd/cve-2026-48998) | guzzlehttp/psr7 | 1.9.1 | 2.10.2 | guzzlehttp/psr7: guzzlehttp/psr7: Information disclosure via improper Host header validation |
| medium | [CVE-2026-49214](https://avd.aquasec.com/nvd/cve-2026-49214) | guzzlehttp/psr7 | 2.6.0 | 2.10.2 | guzzlehttp/psr7 is a PSR-7 HTTP message library implementation in PHP. ... |
| medium | [CVE-2026-49214](https://avd.aquasec.com/nvd/cve-2026-49214) | guzzlehttp/psr7 | 1.9.1 | 2.10.2 | guzzlehttp/psr7 is a PSR-7 HTTP message library implementation in PHP. ... |
| medium | [CVE-2026-55766](https://avd.aquasec.com/nvd/cve-2026-55766) | guzzlehttp/psr7 | 2.6.0 | 2.12.1 | guzzlehttp/psr7 is a PSR-7 HTTP message library implementation in PHP. ... |
| medium | [CVE-2026-55766](https://avd.aquasec.com/nvd/cve-2026-55766) | guzzlehttp/psr7 | 1.9.1 | 2.12.1 | guzzlehttp/psr7 is a PSR-7 HTTP message library implementation in PHP. ... |
| medium | [CVE-2026-59882](https://avd.aquasec.com/nvd/cve-2026-59882) | guzzlehttp/psr7 | 2.6.0 | 2.12.3 | guzzlehttp/psr7 is a PSR-7 HTTP message library implementation in PHP. ... |
| medium | [CVE-2026-59882](https://avd.aquasec.com/nvd/cve-2026-59882) | guzzlehttp/psr7 | 1.9.1 | 2.12.3 | guzzlehttp/psr7 is a PSR-7 HTTP message library implementation in PHP. ... |
| medium | [CVE-2025-22145](https://avd.aquasec.com/nvd/cve-2025-22145) | nesbot/carbon | 2.69.0 | 3.8.4, 2.72.6 | Carbon is an international PHP extension for DateTime. Application pas ... |
| medium | [CVE-2026-24739](https://avd.aquasec.com/nvd/cve-2026-24739) | symfony/process | v5.4.26 | 5.4.51, 6.4.33, 7.3.11, 7.4.5, 8.0.5 | Symfony's incorrect argument escaping under MSYS2/Git Bash can lead to destructive file operations on Windows |
| medium | [CVE-2026-45065](https://avd.aquasec.com/nvd/cve-2026-45065) | symfony/routing | v6.4.18 | 6.2.0, 6.3.0, 7.3.0, 7.4.0, 4.0.0, 5.1.0, 5.2.0, 5.3.0, 7.2.0, 7.4.12, 7.1.0, 8.0.12, 6.4.40, 5.4.0, 5.4.52, 6.1.0, 6.4… | Symfony is a PHP framework for web and console applications and a set  ... |
| medium | [CVE-2026-48784](https://avd.aquasec.com/nvd/cve-2026-48784) | symfony/routing | v6.4.18 | 5.0.0, 6.4.41, 7.3.0, 8.0.13, 3.0.0, 4.0.0, 5.3.0, 5.4.0, 7.1.0, 5.1.0, 5.2.0, 5.4.53, 6.3.0, 7.2.0, 7.4.0, 7.4.13, 6.1… | Symfony is a PHP framework for web and console applications and a set  ... |
