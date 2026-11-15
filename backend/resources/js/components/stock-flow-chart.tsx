import { useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import type { TooltipContentProps } from 'recharts';
import type {
    NameType,
    ValueType,
} from 'recharts/types/component/DefaultTooltipContent';
import { Button } from '@/components/ui/button';
import { dayKeyIn, useTimeZone } from '@/lib/datetime';

export type HourlyFlow = {
    /** UTC hour as "YYYY-MM-DD HH". */
    hour: string;
    units_in: number;
    units_out: number;
};

type DayFlow = {
    key: string;
    label: string;
    longLabel: string;
    units_in: number;
    units_out: number;
};

const DAYS = 30;

const SERIES = [
    {
        key: 'units_in',
        label: 'In (received + returned)',
        color: 'var(--viz-in)',
    },
    { key: 'units_out', label: 'Out (dispatched)', color: 'var(--viz-out)' },
] as const;

/**
 * Group UTC hours into the last 30 days of the timezone (the organization's), today included, with empty days
 * kept at zero.
 */
function toLocalDays(hours: HourlyFlow[], timeZone?: string): DayFlow[] {
    const days = new Map<string, DayFlow>();
    const [year, month, day] = dayKeyIn(new Date(), timeZone)
        .split('-')
        .map(Number);

    for (let offset = DAYS - 1; offset >= 0; offset--) {
        // Noon UTC on that calendar day, labelled in UTC, so the label is that day whatever the browser's zone.
        const date = new Date(Date.UTC(year, month - 1, day - offset, 12));
        const key = date.toISOString().slice(0, 10);

        days.set(key, {
            key,
            label: date.toLocaleDateString(undefined, {
                day: 'numeric',
                month: 'short',
                timeZone: 'UTC',
            }),
            longLabel: date.toLocaleDateString(undefined, {
                weekday: 'short',
                day: 'numeric',
                month: 'short',
                year: 'numeric',
                timeZone: 'UTC',
            }),
            units_in: 0,
            units_out: 0,
        });
    }

    for (const row of hours) {
        const day = days.get(
            dayKeyIn(
                new Date(`${row.hour.replace(' ', 'T')}:00:00Z`),
                timeZone,
            ),
        );

        if (day) {
            day.units_in += row.units_in;
            day.units_out += row.units_out;
        }
    }

    return [...days.values()];
}

function FlowTooltip({
    active,
    payload,
}: TooltipContentProps<ValueType, NameType>) {
    const day = payload?.[0]?.payload as DayFlow | undefined;

    if (!active || !day) {
        return null;
    }

    return (
        <div className="rounded-lg border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md">
            <div className="mb-1 font-medium">{day.longLabel}</div>
            {SERIES.map((series) => (
                <div key={series.key} className="flex items-center gap-2">
                    <span
                        className="size-2.5 rounded-full"
                        style={{ background: series.color }}
                    />
                    <span className="text-muted-foreground">
                        {series.label}
                    </span>
                    <span className="ml-auto pl-4 font-medium tabular-nums">
                        {day[series.key].toLocaleString()}
                    </span>
                </div>
            ))}
        </div>
    );
}

/**
 * Units entering and leaving the organization per local day over the last 30 days, as paired columns,
 * with a legend, a hover tooltip per day and a table view.
 */
export default function StockFlowChart({ hours }: { hours: HourlyFlow[] }) {
    const [showTable, setShowTable] = useState(false);
    const timeZone = useTimeZone();
    const days = toLocalDays(hours, timeZone);
    const hasFlow = days.some((day) => day.units_in > 0 || day.units_out > 0);

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <ul className="flex flex-wrap gap-4 text-xs text-muted-foreground">
                    {SERIES.map((series) => (
                        <li
                            key={series.key}
                            className="flex items-center gap-1.5"
                        >
                            <span
                                className="size-2.5 rounded-sm"
                                style={{ background: series.color }}
                            />
                            {series.label}
                        </li>
                    ))}
                </ul>
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setShowTable((shown) => !shown)}
                >
                    {showTable ? 'Show chart' : 'Show table'}
                </Button>
            </div>

            {showTable ? (
                <div className="max-h-72 overflow-y-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="sticky top-0 border-b bg-muted text-left text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">Day</th>
                                <th className="px-3 py-2 text-right font-medium">
                                    In
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Out
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {[...days].reverse().map((day) => (
                                <tr
                                    key={day.key}
                                    className="border-b last:border-0"
                                >
                                    <td className="px-3 py-1.5">
                                        {day.longLabel}
                                    </td>
                                    <td className="px-3 py-1.5 text-right tabular-nums">
                                        {day.units_in.toLocaleString()}
                                    </td>
                                    <td className="px-3 py-1.5 text-right tabular-nums">
                                        {day.units_out.toLocaleString()}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <div className="relative h-64">
                    {!hasFlow && (
                        <p className="absolute inset-0 z-10 flex items-center justify-center text-sm text-muted-foreground">
                            No stock came in or went out in the last 30 days.
                        </p>
                    )}
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart
                            data={days}
                            barGap={2}
                            barCategoryGap="20%"
                            margin={{ top: 8, right: 8, bottom: 0, left: 0 }}
                            accessibilityLayer
                        >
                            <CartesianGrid
                                vertical={false}
                                stroke="var(--viz-grid)"
                            />
                            <XAxis
                                dataKey="label"
                                tickLine={false}
                                axisLine={{ stroke: 'var(--viz-axis)' }}
                                tick={{
                                    fill: 'var(--viz-muted)',
                                    fontSize: 11,
                                }}
                                interval="preserveStartEnd"
                                minTickGap={24}
                            />
                            <YAxis
                                allowDecimals={false}
                                tickLine={false}
                                axisLine={false}
                                width={40}
                                tick={{
                                    fill: 'var(--viz-muted)',
                                    fontSize: 11,
                                }}
                                tickFormatter={(value: number) =>
                                    value >= 10000
                                        ? `${Math.round(value / 1000)}K`
                                        : value.toLocaleString()
                                }
                            />
                            <Tooltip
                                cursor={{
                                    fill: 'var(--viz-grid)',
                                    opacity: 0.5,
                                }}
                                content={FlowTooltip}
                            />
                            {SERIES.map((series) => (
                                <Bar
                                    key={series.key}
                                    dataKey={series.key}
                                    name={series.label}
                                    fill={series.color}
                                    maxBarSize={12}
                                    radius={[4, 4, 0, 0]}
                                />
                            ))}
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            )}
        </div>
    );
}
