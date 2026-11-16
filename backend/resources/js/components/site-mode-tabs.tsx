import { router, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Presentation, Rocket } from 'lucide-react';
import { useState } from 'react';
import SiteModeController from '@/actions/App/Http/Controllers/Settings/SiteModeController';
import { cn } from '@/lib/utils';

/**
 * Super-admin switch between production and demo mode for the whole site. Styled like the
 * theme tabs above it.
 */
export default function SiteModeTabs() {
    const { demoMode } = usePage().props;
    const [saving, setSaving] = useState(false);

    const tabs: { demo: boolean; icon: LucideIcon; label: string }[] = [
        { demo: false, icon: Rocket, label: 'Production' },
        { demo: true, icon: Presentation, label: 'Demo' },
    ];

    const choose = (demo: boolean) => {
        if (demo === demoMode || saving) {
            return;
        }

        router.put(
            SiteModeController.update.url(),
            { demo_mode: demo },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div
            role="group"
            aria-label="Site mode"
            aria-busy={saving}
            className="inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800"
        >
            {tabs.map(({ demo, icon: Icon, label }) => (
                <button
                    key={label}
                    type="button"
                    aria-pressed={demoMode === demo}
                    disabled={saving}
                    onClick={() => choose(demo)}
                    className={cn(
                        'flex items-center rounded-md px-3.5 py-1.5 transition-colors disabled:opacity-60',
                        demoMode === demo
                            ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                            : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
                    )}
                >
                    <Icon className="-ml-1 h-4 w-4" />
                    <span className="ml-1.5 text-sm">{label}</span>
                </button>
            ))}
        </div>
    );
}
