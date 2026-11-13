import { usePage } from '@inertiajs/react';

/**
 * The timezone dates are shown and entered in: the current organization's on organization pages, otherwise
 * undefined, which means the browser's own timezone.
 */
export function useTimeZone(): string | undefined {
    return usePage().props.organizationTimezone ?? undefined;
}

/**
 * A date such as "13 Nov 2026" in the given timezone.
 */
export function formatDate(
    value: string | null,
    timeZone?: string,
    options: Intl.DateTimeFormatOptions = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    },
): string {
    return value
        ? new Date(value).toLocaleDateString(undefined, {
              ...options,
              timeZone,
          })
        : '—';
}

/**
 * A date and time such as "13 Nov 2026, 14:05" in the given timezone.
 */
export function formatDateTime(
    value: string | null,
    timeZone?: string,
    options: Intl.DateTimeFormatOptions = {
        dateStyle: 'medium',
        timeStyle: 'short',
    },
): string {
    return value
        ? new Date(value).toLocaleString(undefined, { ...options, timeZone })
        : '—';
}

/**
 * formatDate / formatDateTime bound to the page's timezone (see useTimeZone).
 */
export function useDateFormat() {
    const timeZone = useTimeZone();

    return {
        timeZone,
        formatDate: (
            value: string | null,
            options?: Intl.DateTimeFormatOptions,
        ) => formatDate(value, timeZone, options),
        formatDateTime: (
            value: string | null,
            options?: Intl.DateTimeFormatOptions,
        ) => formatDateTime(value, timeZone, options),
    };
}

type WallClock = {
    year: number;
    month: number;
    day: number;
    hour: number;
    minute: number;
    second: number;
};

/**
 * What a wall clock in the timezone shows at an instant.
 */
function wallClockAt(date: Date, timeZone?: string): WallClock {
    const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-US', {
            timeZone,
            hourCycle: 'h23',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        })
            .formatToParts(date)
            .map((part) => [part.type, Number(part.value)]),
    );

    return {
        year: parts.year,
        month: parts.month,
        day: parts.day,
        hour: parts.hour,
        minute: parts.minute,
        second: parts.second,
    };
}

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

/**
 * The calendar day (YYYY-MM-DD) an instant falls on in the timezone.
 */
export function dayKeyIn(date: Date, timeZone?: string): string {
    const clock = wallClockAt(date, timeZone);

    return `${clock.year}-${pad(clock.month)}-${pad(clock.day)}`;
}

/**
 * The current time in the timezone, as a datetime-local input expects it (YYYY-MM-DDTHH:mm).
 */
export function nowForInput(timeZone?: string): string {
    const clock = wallClockAt(new Date(), timeZone);

    return `${clock.year}-${pad(clock.month)}-${pad(clock.day)}T${pad(clock.hour)}:${pad(clock.minute)}`;
}

/**
 * How far the timezone's wall clock is ahead of UTC at an instant, in milliseconds.
 */
function offsetAt(timestamp: number, timeZone: string): number {
    const clock = wallClockAt(new Date(timestamp), timeZone);
    const wallAsUtc = Date.UTC(
        clock.year,
        clock.month - 1,
        clock.day,
        clock.hour,
        clock.minute,
        clock.second,
    );

    return wallAsUtc - (timestamp - (timestamp % 1000));
}

/**
 * Turn a datetime-local value (YYYY-MM-DDTHH:mm), read as a wall-clock time in the timezone, into an ISO
 * instant for the server.
 */
export function inputToIso(value: string, timeZone?: string): string {
    if (!timeZone) {
        return new Date(value).toISOString();
    }

    const [date, time] = value.split('T');
    const [year, month, day] = date.split('-').map(Number);
    const [hour, minute] = time.split(':').map(Number);
    const wallAsUtc = Date.UTC(year, month - 1, day, hour, minute);

    // Correct once more so times next to a daylight-saving change use the offset in force at that moment.
    let timestamp = wallAsUtc - offsetAt(wallAsUtc, timeZone);
    timestamp = wallAsUtc - offsetAt(timestamp, timeZone);

    return new Date(timestamp).toISOString();
}
