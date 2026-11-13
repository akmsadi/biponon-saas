import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

/**
 * Shows the invitation link once, right after sending, so it can also be shared another way.
 */
export default function InvitationLinkDialog({
    link,
    onClose,
}: {
    link: string | null;
    onClose: () => void;
}) {
    const [copied, setCopied] = useState(false);

    function copy() {
        if (!link) {
            return;
        }

        void navigator.clipboard.writeText(link).then(() => setCopied(true));
    }

    return (
        <Dialog
            open={link !== null}
            onOpenChange={(open) => {
                if (!open) {
                    setCopied(false);
                    onClose();
                }
            }}
        >
            <DialogContent>
                <DialogTitle>Invitation link</DialogTitle>
                <DialogDescription>
                    The invitation was emailed. You can also copy this link and
                    send it yourself. It is shown only now; resend the
                    invitation to get a new one.
                </DialogDescription>
                <div className="flex gap-2">
                    <Input
                        readOnly
                        value={link ?? ''}
                        className="font-mono text-xs"
                        onFocus={(event) => event.target.select()}
                    />
                    <Button type="button" variant="outline" onClick={copy}>
                        {copied ? <Check /> : <Copy />}
                        {copied ? 'Copied' : 'Copy'}
                    </Button>
                </div>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            Done
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
