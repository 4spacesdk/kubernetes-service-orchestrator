/**
 * How cpu and memory are written wherever kso shows them: the Pods menu, and the dialog where the
 * limits are set. In one place, so the same pod cannot read as `5m` in one and `0.005` in the
 * other.
 */

/** Millicores as the cluster writes them: `250m`, and whole cores once there is at least one. */
export function cpuText(millicores?: number | null): string {
    if (millicores === undefined || millicores === null) {
        return '-';
    }
    return millicores >= 1000 ? `${(millicores / 1000).toFixed(millicores % 1000 ? 1 : 0)}` : `${millicores}m`;
}

export function memoryText(bytes?: number | null): string {
    if (bytes === undefined || bytes === null) {
        return '-';
    }
    const mib = bytes / 1024 / 1024;
    return mib >= 1024 ? `${(mib / 1024).toFixed(1)}Gi` : `${Math.round(mib)}Mi`;
}

/** How much of the limit is used, as a percentage - null when there is no limit to be near. */
export function shareOfLimit(used?: number | null, limit?: number | null): number | null {
    if (!used || !limit) {
        return null;
    }
    return Math.min(100, Math.round((used / limit) * 100));
}

/** Amber at three quarters of the limit, red at nine tenths. */
export function barColor(share: number | null): string {
    if (share === null) {
        return 'grey';
    }
    return share >= 90 ? 'error' : (share >= 75 ? 'warning' : 'success');
}
