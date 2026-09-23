// Writes public/release.json - the version being built and the first lines of its CHANGELOG
// section - for the prompt that tells a signed-in user a new version is ready. The service
// worker only knows that the files changed; this is what says what changed.
//
//   node scripts/release-notes.mjs <CHANGELOG.md> <version> <out.json>
//
// A version with no section of its own - a development build - gets no notes, and the prompt
// says only that a new version is ready.
import { readFileSync, writeFileSync } from "node:fs";

/** Bullets shown in the prompt; the rest are behind the link to the release. */
const Shown = 4;
/** A bullet longer than this is cut at a word. */
const MaxLength = 120;

export function releaseNotes(changelog, version) {
    const lines = changelog.split("\n");
    const bare = version.replace(/^v/, "");
    const start = lines.findIndex(line => new RegExp(`^## v?${bare.replace(/\./g, "\\.")}(\\s|$)`).test(line));
    if (start === -1) {
        return { version, notes: [], more: 0 };
    }

    const bullets = [];
    let section = null;
    for (const line of lines.slice(start + 1)) {
        if (line.startsWith("## ")) {
            break;
        }
        if (line.startsWith("### ")) {
            section = line.slice(4).trim();
            continue;
        }
        // The upgrade guide is for whoever deploys it, not for whoever reloads.
        if (line.startsWith("* ") && section !== "Upgrade guide") {
            bullets.push({ section, text: shorten(plain(line.slice(2))) });
        }
    }

    return { version, notes: bullets.slice(0, Shown), more: Math.max(0, bullets.length - Shown) };
}

/** Markdown to what a line of text shows: links become their text, code its content. */
function plain(text) {
    return text
        .replace(/\[([^\]]+)\]\([^)]+\)/g, "$1")
        .replace(/`([^`]+)`/g, "$1")
        .replace(/\*\*([^*]+)\*\*/g, "$1")
        .trim();
}

function shorten(text) {
    if (text.length <= MaxLength) {
        return text;
    }
    const cut = text.slice(0, MaxLength);
    return cut.slice(0, cut.lastIndexOf(" ")).replace(/[\s,;:-]+$/, "") + "…";
}

if (process.argv[1] && import.meta.url.endsWith(process.argv[1].split("/").pop())) {
    const [changelogPath, version, out] = process.argv.slice(2);
    const release = releaseNotes(readFileSync(changelogPath, "utf8"), version ?? "");
    writeFileSync(out, JSON.stringify(release, null, 2) + "\n");
    console.log(`release.json: ${release.version}, ${release.notes.length} notes, ${release.more} more`);
}
