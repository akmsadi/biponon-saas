import { Form } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import { useState } from 'react';
import AccessRequestController from '@/actions/App/Http/Controllers/AccessRequestController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { LandingLanguage } from '@/hooks/use-landing-language';
import { landingCopy } from '@/lib/landing-copy';

const textareaClassName =
    'flex w-full rounded-md border border-input bg-background px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm';

/**
 * The landing page's request access form. Saved for super-admins to read under Access requests.
 */
export function RequestAccessForm({ language }: { language: LandingLanguage }) {
    const copy = landingCopy[language].form;
    const [sent, setSent] = useState(false);

    // Server messages are English; Bangla visitors get one plain Bangla line instead.
    const fieldError = (message?: string) =>
        message && (language === 'en' ? message : copy.invalidField);

    if (sent) {
        return (
            <div
                role="status"
                className="flex flex-col items-center gap-3 rounded-xl border bg-card p-8 text-center"
            >
                <CircleCheck className="size-10 text-[var(--viz-in)]" />
                <h3 className="text-lg font-semibold">{copy.thanksTitle}</h3>
                <p className="text-muted-foreground">{copy.thanksBody}</p>
            </div>
        );
    }

    return (
        <Form
            {...AccessRequestController.store.form()}
            options={{ preserveScroll: true }}
            onSuccess={(page) => {
                if (page.flash.accessRequested) {
                    setSent(true);
                }
            }}
            className="grid gap-4 rounded-xl border bg-card p-6 text-left sm:grid-cols-2 sm:p-8"
        >
            {({ processing, errors }) => (
                <>
                    <input type="hidden" name="language" value={language} />

                    {/* Honeypot: invisible to people, so anything typed here came from a bot. */}
                    <div aria-hidden="true" className="absolute -left-[9999px]">
                        <label htmlFor="request-website">Website</label>
                        <input
                            id="request-website"
                            name="website"
                            type="text"
                            tabIndex={-1}
                            autoComplete="off"
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="request-name">{copy.name}</Label>
                        <Input
                            id="request-name"
                            name="name"
                            required
                            maxLength={255}
                            autoComplete="name"
                        />
                        <InputError message={fieldError(errors.name)} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="request-company">{copy.company}</Label>
                        <Input
                            id="request-company"
                            name="company"
                            required
                            maxLength={255}
                            autoComplete="organization"
                        />
                        <InputError message={fieldError(errors.company)} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="request-email">{copy.email}</Label>
                        <Input
                            id="request-email"
                            name="email"
                            type="email"
                            required
                            maxLength={255}
                            autoComplete="email"
                        />
                        <InputError message={fieldError(errors.email)} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="request-phone">
                            {copy.phone}{' '}
                            <span className="font-normal text-muted-foreground">
                                ({copy.optional})
                            </span>
                        </Label>
                        <Input
                            id="request-phone"
                            name="phone"
                            type="tel"
                            maxLength={50}
                            autoComplete="tel"
                        />
                        <InputError message={fieldError(errors.phone)} />
                    </div>

                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="request-message">
                            {copy.message}{' '}
                            <span className="font-normal text-muted-foreground">
                                ({copy.optional})
                            </span>
                        </Label>
                        <textarea
                            id="request-message"
                            name="message"
                            rows={4}
                            maxLength={2000}
                            className={textareaClassName}
                        />
                        <InputError message={fieldError(errors.message)} />
                    </div>

                    <div className="flex flex-col items-start gap-3 sm:col-span-2">
                        {errors.form === 'too_many_attempts' && (
                            <p
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {copy.tooManyAttempts}
                            </p>
                        )}
                        <Button type="submit" size="lg" disabled={processing}>
                            {processing && <Spinner />}
                            {processing ? copy.sending : copy.submit}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
