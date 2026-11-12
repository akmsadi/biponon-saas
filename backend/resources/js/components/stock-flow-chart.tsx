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

function localDayKey(date: Date): string {
    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}

/**
 * Group UTC hours into the browser's last 30 local days, today included, with empty days kept at zero.
 */
function toLocalDays(hours: HourlyFlow[]): DayFlow[] {
    const days = new Map<string, DayFlow>();
    const today = new Date();

    for (let offset = DAYS - 1; offset >= 0; offset--) {
        const date = new Date(
            today.getFullYear(),
            today.getMonth(),
            today.getDate() - offset,
        );

        days.set(localDayKey(date), {
            key: localDayKey(date),
            label: date.toLocaleDateString(undefined, {
                day: 'numeric',
                month: 'short',
            }),
            longLabel: date.toLocaleDateString(undefined, {
                weekday: 'short',
                day: 'numeric',
                month: 'short',
                year: 'numeric',
            }),
            units_in: 0,
            units_out: 0,
        });
    }

    for (const row of hours) {
        const day = days.get(
            localDayKey(new Date(`${row.hour.replace(' ', 'T')}:00:00Z`)),
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
    const days = toLocalDays(hours);
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
