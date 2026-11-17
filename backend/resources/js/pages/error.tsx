import { Head, Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type ErrorStatus = 403 | 404 | 500 | 503;

const MESSAGES: Record<ErrorStatus, { title: string; description: string }> = {
    403: {
        title: "You don't have access to this page",
        description:
            "Your role in this organization doesn't allow it. Ask an admin if you need access.",
    },
    404: {
        title: 'Page not found',
        description:
            'The page you are looking for does not exist or has been moved.',
    },
    500: {
        title: 'Something went wrong',
        description: 'The error has been logged. Please try again in a moment.',
    },
    503: {
        title: 'Down for maintenance',
        description:
            'Biponon is being updated. Please try again in a few minutes.',
    },
};

/**
 * Stand-alone page (no app layout) so it also works for visitors who are not signed in
 * and for URLs that match no route, where shared props are not available.
 */
export default function ErrorPage({ status }: { status: ErrorStatus }) {
    const message = MESSAGES[status] ?? MESSAGES[500];

    return (
        <>
            <Head title={message.title} />

            <main className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 text-center">
                <AppLogoIcon className="size-10 fill-current text-foreground" />

                <div className="grid gap-2">
                    <p className="text-sm font-medium text-muted-foreground">
                        Error {status}
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {message.title}
                    </h1>
                    <p className="max-w-md text-sm text-muted-foreground">
                        {message.description}
                    </p>
                </div>

                <div className="flex flex-wrap justify-center gap-3">
                    <Button
                        variant="outline"
                        onClick={() => window.history.back()}
                    >
                        Go back
                    </Button>
                    <Button asChild>
                        <Link href={dashboard()}>Go to dashboard</Link>
                    </Button>
                </div>
            </main>
        </>
    );
}
