import { Head, Link, usePage } from '@inertiajs/react';
import {
    ChartColumn,
    ScanBarcode,
    ShieldCheck,
    ShoppingCart,
    Store,
    Warehouse,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { DemoPanel } from '@/components/demo-panel';
import { DashboardPreview } from '@/components/landing/dashboard-preview';
import { RequestAccessForm } from '@/components/landing/request-access-form';
import { Button } from '@/components/ui/button';
import type { LandingLanguage } from '@/hooks/use-landing-language';
import { useLandingLanguage } from '@/hooks/use-landing-language';
import type { LandingFeatureKey } from '@/lib/landing-copy';
import { landingCopy } from '@/lib/landing-copy';
import { cn } from '@/lib/utils';
import { dashboard, login } from '@/routes';

const FEATURE_ICONS: Record<LandingFeatureKey, LucideIcon> = {
    stock: Warehouse,
    serials: ScanBarcode,
    purchasing: ShoppingCart,
    sales: Store,
    reports: ChartColumn,
    roles: ShieldCheck,
};

const LANGUAGES: { value: LandingLanguage; label: string }[] = [
    { value: 'en', label: 'EN' },
    { value: 'bn', label: 'বাং' },
];

export default function Welcome() {
    const { auth, name } = usePage().props;
    const { language, updateLanguage } = useLandingLanguage();
    const copy = landingCopy[language];

    useEffect(() => {
        document.documentElement.lang = language;

        return () => {
            document.documentElement.lang = 'en';
        };
    }, [language]);

    const accountLink = auth.user ? (
        <Button asChild>
            <Link href={dashboard()}>{copy.nav.dashboard}</Link>
        </Button>
    ) : (
        <Button asChild variant="outline">
            <Link href={login()}>{copy.nav.logIn}</Link>
        </Button>
    );

    return (
        <>
            <Head title={copy.meta.title}>
                <meta name="description" content={copy.meta.description} />
            </Head>

            <div className="min-h-screen bg-background text-foreground">
                <header className="sticky top-0 z-30 border-b bg-background/80 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                    <div className="mx-auto flex h-16 max-w-6xl items-center gap-4 px-4 sm:px-6">
                        <a
                            href="#top"
                            className="flex items-center gap-2 font-semibold"
                        >
                            <span className="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                                <AppLogoIcon className="size-5 fill-current" />
                            </span>
                            {name}
                        </a>

                        <nav className="ml-6 hidden items-center gap-6 text-sm text-muted-foreground md:flex">
                            <a
                                href="#features"
                                className="transition-colors hover:text-foreground"
                            >
                                {copy.nav.features}
                            </a>
                            <a
                                href="#how-it-works"
                                className="transition-colors hover:text-foreground"
                            >
                                {copy.nav.howItWorks}
                            </a>
                        </nav>

                        <div className="ml-auto flex items-center gap-2 sm:gap-3">
                            <div
                                role="group"
                                aria-label={copy.nav.language}
                                className="flex rounded-md border p-0.5 text-xs"
                            >
                                {LANGUAGES.map((option) => (
                                    <button
                                        key={option.value}
                                        type="button"
                                        aria-pressed={language === option.value}
                                        lang={option.value}
                                        onClick={() =>
                                            updateLanguage(option.value)
                                        }
                                        className={cn(
                                            'rounded-sm px-2 py-1 font-medium transition-colors',
                                            language === option.value
                                                ? 'bg-primary text-primary-foreground'
                                                : 'text-muted-foreground hover:text-foreground',
                                        )}
                                    >
                                        {option.label}
                                    </button>
                                ))}
                            </div>
                            {accountLink}
                        </div>
                    </div>
                </header>

                <main id="top">
                    <section className="relative overflow-hidden">
                        <div
                            aria-hidden="true"
                            className="pointer-events-none absolute inset-x-0 -top-40 h-[32rem] bg-[radial-gradient(ellipse_at_top,var(--color-muted),transparent_70%)]"
                        />
                        <div className="relative mx-auto grid max-w-6xl items-center gap-12 px-4 py-16 sm:px-6 sm:py-24 lg:grid-cols-2 lg:gap-16">
                            <div>
                                <p className="inline-flex rounded-full border bg-background px-3 py-1 text-xs font-medium text-muted-foreground">
                                    {copy.hero.eyebrow}
                                </p>
                                <h1 className="mt-5 text-4xl leading-tight font-semibold tracking-tight text-balance sm:text-5xl">
                                    {copy.hero.title}
                                </h1>
                                <p className="mt-5 max-w-xl text-lg text-pretty text-muted-foreground">
                                    {copy.hero.subtitle}
                                </p>
                                <div className="mt-8 flex flex-wrap gap-3">
                                    <Button asChild size="lg">
                                        <a href="#request-access">
                                            {copy.nav.requestAccess}
                                        </a>
                                    </Button>
                                    {!auth.user && (
                                        <Button
                                            asChild
                                            size="lg"
                                            variant="outline"
                                        >
                                            <Link href={login()}>
                                                {copy.nav.logIn}
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                                <p className="mt-4 text-sm text-muted-foreground">
                                    {copy.hero.note}
                                </p>
                            </div>
                            <DashboardPreview language={language} />
                        </div>
                    </section>

                    <section
                        id="features"
                        className="scroll-mt-16 border-t bg-muted/40"
                    >
                        <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
                            <div className="max-w-2xl">
                                <h2 className="text-3xl font-semibold tracking-tight text-balance">
                                    {copy.features.title}
                                </h2>
                                <p className="mt-3 text-muted-foreground">
                                    {copy.features.subtitle}
                                </p>
                            </div>
                            <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {(
                                    Object.keys(
                                        FEATURE_ICONS,
                                    ) as LandingFeatureKey[]
                                ).map((key) => {
                                    const Icon = FEATURE_ICONS[key];
                                    const feature = copy.features.items[key];

                                    return (
                                        <div
                                            key={key}
                                            className="rounded-xl border bg-card p-6"
                                        >
                                            <span className="flex size-10 items-center justify-center rounded-lg border bg-background">
                                                <Icon className="size-5" />
                                            </span>
                                            <h3 className="mt-4 font-semibold">
                                                {feature.title}
                                            </h3>
                                            <p className="mt-2 text-sm text-muted-foreground">
                                                {feature.body}
                                            </p>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </section>

                    <section
                        id="how-it-works"
                        className="scroll-mt-16 border-t"
                    >
                        <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
                            <h2 className="max-w-2xl text-3xl font-semibold tracking-tight text-balance">
                                {copy.steps.title}
                            </h2>
                            <ol className="mt-10 grid gap-8 md:grid-cols-3">
                                {copy.steps.items.map((step, index) => (
                                    <li key={index}>
                                        <span className="flex size-9 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">
                                            {(index + 1).toLocaleString(
                                                language === 'bn'
                                                    ? 'bn-BD'
                                                    : 'en-US',
                                            )}
                                        </span>
                                        <h3 className="mt-4 font-semibold">
                                            {step.title}
                                        </h3>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {step.body}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </section>

                    <section
                        id="request-access"
                        className="scroll-mt-16 border-t bg-muted/40"
                    >
                        <div className="mx-auto max-w-2xl px-4 py-16 sm:px-6 sm:py-24">
                            <div className="text-center">
                                <h2 className="text-3xl font-semibold tracking-tight text-balance">
                                    {copy.requestAccess.title}
                                </h2>
                                <p className="mt-3 text-muted-foreground">
                                    {copy.requestAccess.body}
                                </p>
                            </div>
                            <div className="relative mt-10">
                                <RequestAccessForm language={language} />
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="border-t">
                    <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-4 py-6 text-sm text-muted-foreground sm:flex-row sm:px-6">
                        <span>
                            © {new Date().getFullYear()} {name}.{' '}
                            {copy.footer.rights}
                        </span>
                    </div>
                </footer>
                <DemoPanel />
            </div>
        </>
    );
}
