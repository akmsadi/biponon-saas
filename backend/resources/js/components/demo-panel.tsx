import { usePage } from '@inertiajs/react';
import { ChevronUp, Code2, FileCode, Github } from 'lucide-react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DEMO_ARCHITECTURE,
    DEMO_PAGE_NOTES,
    DEMO_REPOSITORY_URL,
    DEMO_STACK,
    demoSourceUrl,
} from '@/lib/demo-notes';
import { cn } from '@/lib/utils';

/** A highlighter-pen stroke behind the lower half of a heading. */
const HIGHLIGHT =
    'bg-[linear-gradient(transparent_45%,var(--color-amber-300)_45%)] box-decoration-clone px-1 text-inherit dark:bg-[linear-gradient(transparent_45%,var(--color-amber-700)_45%)]';

/**
 * A full-width bar stuck to the bottom of the screen: on the landing and login pages while the
 * site is in demo mode, and inside the app for the shared demo accounts. It shows the technology behind Biponon and, where written, how the current page
 * works. It is sticky rather than fixed, so inside the app it stays in the content column and
 * never covers the sidebar, and it takes up its own space so it never hides the page's end.
 */
export function DemoPanel({
    className,
    inApp = false,
}: {
    className?: string;
    /** Inside the app it is shown to the shared demo accounts only; elsewhere it follows the site mode. */
    inApp?: boolean;
}) {
    const { component, props } = usePage();

    const visible = inApp ? props.auth.user?.is_demo === true : props.demoMode;

    if (!visible) {
        return null;
    }

    const note = DEMO_PAGE_NOTES[component];

    return (
        <aside
            aria-label="How this app is built"
            lang="en"
            className={cn(
                'sticky bottom-0 z-40 border-t-2 border-amber-400 bg-amber-50 text-sm text-amber-950 shadow-[0_-4px_12px_rgb(0_0_0/0.06)] dark:border-amber-500/60 dark:bg-amber-950/60 dark:text-amber-50',
                className,
            )}
        >
            <Collapsible className="mx-auto w-full max-w-6xl px-4 py-3 sm:px-6">
                <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-400 px-2.5 py-0.5 text-xs font-semibold text-amber-950">
                        <Code2 className="size-3.5" />
                        Demo
                    </span>
                    <span className="hidden min-w-0 flex-1 truncate text-amber-900/80 sm:block dark:text-amber-100/80">
                        Built with{' '}
                        {DEMO_STACK.slice(0, 5)
                            .map((item) => item.name)
                            .join(' · ')}
                    </span>
                    <a
                        href={DEMO_REPOSITORY_URL}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1.5 text-amber-900/80 transition-colors hover:text-amber-950 max-sm:ml-auto dark:text-amber-100/80 dark:hover:text-amber-50"
                    >
                        <Github className="size-4" />
                        <span className="max-sm:sr-only">Source on GitHub</span>
                    </a>
                    <CollapsibleTrigger className="group inline-flex items-center gap-1 rounded-md px-2 py-1 font-medium transition-colors hover:bg-amber-200/70 dark:hover:bg-amber-800/50">
                        {note ? 'How this page is built' : 'How it is built'}
                        <ChevronUp className="size-4 transition-transform group-data-[state=open]:rotate-180" />
                    </CollapsibleTrigger>
                </div>

                <CollapsibleContent className="max-h-[60vh] overflow-y-auto overscroll-contain">
                    <div className="grid gap-6 pt-4 pb-2 lg:grid-cols-2">
                        {note && (
                            <section className="lg:col-span-2">
                                <h2 className="font-semibold">
                                    <mark className={HIGHLIGHT}>
                                        This page: {note.title}
                                    </mark>
                                </h2>
                                <ul className="mt-2 list-disc space-y-1.5 pl-5 text-muted-foreground">
                                    {note.points.map((point) => (
                                        <li key={point}>{point}</li>
                                    ))}
                                </ul>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {note.files.map((file) => (
                                        <a
                                            key={file}
                                            href={demoSourceUrl(file)}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex max-w-full items-center gap-1.5 rounded-md border bg-background px-2 py-1 font-mono text-xs text-muted-foreground transition-colors hover:text-foreground"
                                        >
                                            <FileCode className="size-3.5 shrink-0" />
                                            <span className="truncate">
                                                {file}
                                            </span>
                                        </a>
                                    ))}
                                </div>
                            </section>
                        )}

                        <section>
                            <h2 className="font-semibold">
                                <mark className={HIGHLIGHT}>Technology</mark>
                            </h2>
                            <dl className="mt-2 grid gap-x-4 gap-y-1.5 sm:grid-cols-[auto_1fr]">
                                {DEMO_STACK.map((item) => (
                                    <div key={item.name} className="contents">
                                        <dt className="font-medium">
                                            {item.name}
                                        </dt>
                                        <dd className="text-muted-foreground">
                                            {item.role}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </section>

                        <section>
                            <h2 className="font-semibold">
                                <mark className={HIGHLIGHT}>Architecture</mark>
                            </h2>
                            <ul className="mt-2 list-disc space-y-1.5 pl-5 text-muted-foreground">
                                {DEMO_ARCHITECTURE.map((point) => (
                                    <li key={point}>{point}</li>
                                ))}
                            </ul>
                        </section>
                    </div>
                </CollapsibleContent>
            </Collapsible>
        </aside>
    );
}
