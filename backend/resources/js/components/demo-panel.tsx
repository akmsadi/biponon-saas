import { usePage } from '@inertiajs/react';
import { ChevronDown, Code2, FileCode, Github } from 'lucide-react';
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

/**
 * Shown at the bottom of every page while a super-admin has the site in demo mode: the
 * technology behind Biponon and, where written, how the current page works.
 */
export function DemoPanel({ className }: { className?: string }) {
    const { component, props } = usePage();

    if (!props.demoMode) {
        return null;
    }

    const note = DEMO_PAGE_NOTES[component];

    return (
        <aside
            aria-label="How this app is built"
            lang="en"
            className={cn('border-t bg-muted/50 text-sm', className)}
        >
            <Collapsible className="mx-auto w-full max-w-6xl px-4 py-3 sm:px-6">
                <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <span className="inline-flex items-center gap-1.5 rounded-full border bg-background px-2.5 py-0.5 text-xs font-medium">
                        <Code2 className="size-3.5" />
                        Demo
                    </span>
                    <span className="min-w-0 flex-1 truncate text-muted-foreground">
                        Built with{' '}
                        {DEMO_STACK.slice(0, 5)
                            .map((item) => item.name)
                            .join(' · ')}
                    </span>
                    <a
                        href={DEMO_REPOSITORY_URL}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1.5 text-muted-foreground transition-colors hover:text-foreground"
                    >
                        <Github className="size-4" />
                        Source on GitHub
                    </a>
                    <CollapsibleTrigger className="group inline-flex items-center gap-1 rounded-md px-2 py-1 font-medium transition-colors hover:bg-background">
                        {note ? 'How this page is built' : 'How it is built'}
                        <ChevronDown className="size-4 transition-transform group-data-[state=open]:rotate-180" />
                    </CollapsibleTrigger>
                </div>

                <CollapsibleContent className="grid gap-6 pt-4 pb-2 lg:grid-cols-2">
                    {note && (
                        <section className="lg:col-span-2">
                            <h2 className="font-semibold">
                                This page: {note.title}
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
                                        <span className="truncate">{file}</span>
                                    </a>
                                ))}
                            </div>
                        </section>
                    )}

                    <section>
                        <h2 className="font-semibold">Technology</h2>
                        <dl className="mt-2 grid gap-x-4 gap-y-1.5 sm:grid-cols-[auto_1fr]">
                            {DEMO_STACK.map((item) => (
                                <div key={item.name} className="contents">
                                    <dt className="font-medium">{item.name}</dt>
                                    <dd className="text-muted-foreground">
                                        {item.role}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </section>

                    <section>
                        <h2 className="font-semibold">Architecture</h2>
                        <ul className="mt-2 list-disc space-y-1.5 pl-5 text-muted-foreground">
                            {DEMO_ARCHITECTURE.map((point) => (
                                <li key={point}>{point}</li>
                            ))}
                        </ul>
                    </section>
                </CollapsibleContent>
            </Collapsible>
        </aside>
    );
}
