import { Card, CardContent } from '@/components/ui/card';
import { UnreadNotificationDot } from '@/components/UnreadNotificationDot';
import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

interface ReportCardProps {
    label: string;
    description: string;
    href: string;
    icon: LucideIcon;
    unreadLabel?: string;
}

export function ReportCard({ label, description, href, icon: Icon, unreadLabel }: ReportCardProps) {
    return (
        <Link href={href} className="block h-full">
            <Card className="h-full cursor-pointer transition-all hover:-translate-y-0.5 hover:shadow-md">
                <CardContent className="flex h-full min-h-28 items-start gap-4 p-5">
                    <div className="relative flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Icon className="size-5" />
                        {unreadLabel ? (
                            <UnreadNotificationDot
                                label={unreadLabel}
                                className="absolute -top-0.5 -left-0.5 size-1.5 ring-2 ring-background"
                            />
                        ) : null}
                    </div>

                    <div className="min-w-0">
                        <h3 className="font-semibold">{label}</h3>

                        <p className="mt-1 min-h-10 text-sm leading-5 text-muted-foreground">{description}</p>
                    </div>
                </CardContent>
            </Card>
        </Link>
    );
}
