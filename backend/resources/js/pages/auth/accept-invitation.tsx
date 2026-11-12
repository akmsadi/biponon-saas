import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login, logout } from '@/routes';
import { accept, register } from '@/routes/invitations';

type Invitation = {
    email: string;
    role: string;
    organization: string;
    invited_by: string | null;
    expires_at: string;
};

type Props =
    | { state: 'invalid'; token: string }
    | {
          state: 'accept' | 'wrong_account' | 'login' | 'register';
          token: string;
          invitation: Invitation;
          signedInAs: string | null;
          passwordRules: string;
      };

export default function AcceptInvitation(props: Props) {
    if (props.state === 'invalid') {
        return (
            <>
                <Head title="Invitation" />
                <p className="text-center text-sm text-muted-foreground">
                    This invitation link is invalid, has expired or was already
                    used. Ask an admin of the organization to send you a new
                    one.
                </p>
            </>
        );
    }

    const { state, token, invitation, signedInAs, passwordRules } = props;

    return (
        <>
            <Head title={`Join ${invitation.organization}`} />

            <div className="flex flex-col gap-6">
                <p className="text-center text-sm">
                    {invitation.invited_by ?? 'An admin'} invited{' '}
                    <span className="font-medium">{invitation.email}</span> to
                    join{' '}
                    <span className="font-medium">
                        {invitation.organization}
                    </span>{' '}
                    as {invitation.role}.
                </p>

                {state === 'accept' && (
                    <Form {...accept.form(token)}>
                        {({ processing, errors }) => (
                            <div className="grid gap-2">
                                <Button
                                    className="w-full"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Accept invitation
                                </Button>
                                <InputError message={errors.invitation} />
                            </div>
                        )}
                    </Form>
                )}

                {state === 'wrong_account' && (
                    <div className="grid gap-4 text-center text-sm text-muted-foreground">
                        <p>
                            You are logged in as {signedInAs}. Log out, then
                            open the link again to accept as {invitation.email}.
                        </p>
                        <Button variant="outline" asChild>
                            <Link href={logout()} as="button">
                                Log out
                            </Link>
                        </Button>
                    </div>
                )}

                {state === 'login' && (
                    <div className="grid gap-4 text-center text-sm text-muted-foreground">
                        <p>
                            You already have an account. Log in as{' '}
                            {invitation.email} to accept; you will come back
                            here afterwards.
                        </p>
                        <Button asChild>
                            <Link href={login()}>Log in</Link>
                        </Button>
                    </div>
                )}

                {state === 'register' && (
                    <Form
                        {...register.form(token)}
                        resetOnSuccess={['password', 'password_confirmation']}
                        className="grid gap-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <p className="text-center text-sm text-muted-foreground">
                                    Create your account to accept.
                                </p>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={invitation.email}
                                        readOnly
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        autoFocus
                                        maxLength={255}
                                        autoComplete="name"
                                        placeholder="Full name"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password">Password</Label>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        autoComplete="new-password"
                                        placeholder="Password"
                                        passwordrules={passwordRules}
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation">
                                        Confirm password
                                    </Label>
                                    <PasswordInput
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        required
                                        autoComplete="new-password"
                                        placeholder="Confirm password"
                                        passwordrules={passwordRules}
                                    />
                                    <InputError
                                        message={errors.password_confirmation}
                                    />
                                </div>

                                <InputError message={errors.invitation} />

                                <Button
                                    className="w-full"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Create account and join
                                </Button>

                                <p className="text-center text-xs text-muted-foreground">
                                    Already have an account under another email?
                                    Ask for the invitation to be sent there, or{' '}
                                    <TextLink href={login()}>log in</TextLink>.
                                </p>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}

AcceptInvitation.layout = {
    title: 'Organization invitation',
    description: '',
};
