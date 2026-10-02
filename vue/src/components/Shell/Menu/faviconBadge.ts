/**
 * A number on the browser tab's icon, the way a mail client shows unread mail - so updates are
 * noticed from another tab. Drawn onto the icon kso already has; none puts the plain icon back.
 */

const Size = 64;

let plainIcon: string | null = null;
let drawn: number | null = null;

export function showFaviconCount(count: number) {
    const link = document.querySelector<HTMLLinkElement>('link[rel="icon"]');
    if (!link || count === drawn) {
        return;
    }
    plainIcon ??= link.href;
    drawn = count;

    if (count <= 0) {
        link.href = plainIcon;
        return;
    }

    const icon = new Image();
    icon.onload = () => {
        // A newer count was asked for while this one loaded.
        if (drawn !== count) {
            return;
        }
        const canvas = document.createElement('canvas');
        canvas.width = Size;
        canvas.height = Size;
        const context = canvas.getContext('2d');
        if (!context) {
            return;
        }
        context.drawImage(icon, 0, 0, Size, Size);

        const text = count > 9 ? '9+' : String(count);
        const radius = Size * 0.3;
        const x = Size - radius;
        const y = radius;
        context.beginPath();
        context.arc(x, y, radius, 0, 2 * Math.PI);
        context.fillStyle = '#d32f2f';
        context.fill();
        context.fillStyle = '#ffffff';
        context.font = `bold ${text.length > 1 ? Size * 0.34 : Size * 0.42}px sans-serif`;
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.fillText(text, x, y + 2);

        link.href = canvas.toDataURL('image/png');
    };
    icon.src = plainIcon;
}
