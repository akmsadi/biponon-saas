import { cn } from '@/lib/utils';

export default function ColorSwatch({
    hex,
    className,
}: {
    hex: string | null;
    className?: string;
}) {
    if (!hex) {
        return (
            <span
                className={cn(
                    'inline-block size-5 shrink-0 rounded border border-dashed border-muted-foreground/40',
                    className,
                )}
            />
        );
    }

    return (
        <span
            className={cn(
                'inline-block size-5 shrink-0 rounded border border-black/10 dark:border-white/20',
                className,
            )}
            style={{ backgroundColor: hex }}
        />
    );
}
