import { ArrowDownLeft, ArrowLeftRight, ArrowUpRight } from 'lucide-react';
import type { LandingLanguage } from '@/hooks/use-landing-language';
import { landingCopy } from '@/lib/landing-copy';
import { cn } from '@/lib/utils';

/** Made-up week of stock in / out, as a share of the tallest bar. */
const WEEK = [
    { in: 62, out: 40 },
    { in: 35, out: 52 },
    { in: 80, out: 46 },
    { in: 44, out: 70 },
    { in: 92, out: 58 },
    { in: 50, out: 64 },
    { in: 74, out: 85 },
];

/**
 * A decorative picture of the dashboard for the landing page, drawn with HTML so it
 * follows the theme and language and never goes out of date like a screenshot.
 */
export function DashboardPreview({ language }: { language: LandingLanguage }) {
    const copy = landingCopy[language].preview;
    const number = (value: number) =>
        value.toLocaleString(language === 'bn' ? 'bn-BD' : 'en-US');

    const tiles = [
        { label: copy.unitsInStock, value: number(12480) },
        { label: copy.lowStock, value: number(7), warn: true },
        { label: copy.warehouses, value: number(4) },
    ];

    const movements = [
        {
            icon: ArrowDownLeft,
            label: copy.received,
            reference: 'PO-0042',
            quantity: `+${number(240)}`,
            tone: 'text-[var(--viz-in)]',
        },
        {
            icon: ArrowUpRight,
            label: copy.dispatched,
            reference: 'SO-0118',
            quantity: `−${number(36)}`,
            tone: 'text-[var(--viz-out)]',
        },
        {
            icon: ArrowLeftRight,
            label: copy.transferred,
            reference: 'GUL → CWH',
            quantity: number(50),
            tone: 'text-muted-foreground',
        },
    ];

    return (
        <div
            aria-hidden="true"
            className="w-full rounded-xl border bg-card p-4 shadow-xl shadow-black/5 select-none sm:p-5 dark:shadow-black/40"
        >
            <div className="mb-4 flex items-center gap-1.5">
                <span className="size-2.5 rounded-full bg-border" />
                <span className="size-2.5 rounded-full bg-border" />
                <span className="size-2.5 rounded-full bg-border" />
            </div>

            <div className="grid grid-cols-3 gap-2 sm:gap-3">
                {tiles.map((tile) => (
                    <div
                        key={tile.label}
                        className="rounded-lg border bg-background p-2.5 sm:p-3"
                    >
                        <div className="truncate text-[11px] text-muted-foreground sm:text-xs">
                            {tile.label}
                        </div>
                        <div
                            className={cn(
                                'mt-1 text-base font-semibold tabular-nums sm:text-xl',
                                tile.warn && 'text-[var(--viz-status-serious)]',
                            )}
                        >
                            {tile.value}
                        </div>
                    </div>
                ))}
            </div>

            <div className="mt-3 rounded-lg border bg-background p-3">
                <div className="flex items-center justify-between text-xs text-muted-foreground">
                    <span>{copy.lastSevenDays}</span>
                    <span className="flex items-center gap-3">
                        <span className="flex items-center gap-1">
                            <span className="size-2 rounded-sm bg-[var(--viz-in)]" />
                            {copy.stockIn}
                        </span>
                        <span className="flex items-center gap-1">
                            <span className="size-2 rounded-sm bg-[var(--viz-out)]" />
                            {copy.stockOut}
                        </span>
                    </span>
                </div>
                <div className="mt-3 flex h-24 items-end gap-2 border-b border-[var(--viz-grid)] sm:h-28">
                    {WEEK.map((day, index) => (
                        <div
                            key={index}
                            className="flex h-full flex-1 items-end justify-center gap-0.5"
                        >
                            <span
                                className="w-full max-w-3 rounded-t-sm bg-[var(--viz-in)]"
                                style={{ height: `${day.in}%` }}
                            />
                            <span
                                className="w-full max-w-3 rounded-t-sm bg-[var(--viz-out)]"
                                style={{ height: `${day.out}%` }}
                            />
                        </div>
                    ))}
                </div>
            </div>

            <div className="mt-3 rounded-lg border bg-background">
                <div className="border-b px-3 py-2 text-xs text-muted-foreground">
                    {copy.recentMovements}
                </div>
                <ul className="divide-y">
                    {movements.map((movement) => (
                        <li
                            key={movement.reference}
                            className="flex items-center gap-2.5 px-3 py-2 text-xs sm:text-sm"
                        >
                            <movement.icon
                                className={cn('size-4 shrink-0', movement.tone)}
                            />
                            <span className="font-medium">
                                {movement.label}
                            </span>
                            <span className="truncate font-mono text-xs text-muted-foreground">
                                {movement.reference}
                            </span>
                            <span className="ml-auto shrink-0 tabular-nums">
                                {movement.quantity}{' '}
                                <span className="text-muted-foreground">
                                    {copy.units}
                                </span>
                            </span>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}
