import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import * as passes from '@/routes/passes';
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-react';
import { Pass } from '@/types/pass';
import DistributionPanel from './DistributionPanel';

interface PassesDistributionLinksProps {
    pass: Pass;
}

export default function PassesDistributionLinks({
    pass,
}: PassesDistributionLinksProps) {
    return (
        <AppLayout
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={passes.show({ pass: pass.id }).url}>
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back
                            </Link>
                        </Button>
                        <div>
                            <h2 className="text-xl font-semibold">
                                Share Pass
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Manage distribution links for{' '}
                                {pass.pass_data.description || 'Untitled Pass'}
                            </p>
                        </div>
                    </div>
                </div>
            }
        >
            <Head title={`Share Pass: ${pass.serial_number}`} />

            <div className="mx-auto max-w-4xl">
                <DistributionPanel pass={pass} />
            </div>
        </AppLayout>
    );
}
