import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/useTranslation';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowLeft,
    ArrowUpRight,
    CircleDollarSign,
    CreditCard,
    Receipt,
    WalletCards,
} from 'lucide-react';

const summary = [
    { label: 'Collected this month', value: 'MMK 48.2M', change: '+12.8%', icon: CircleDollarSign, positive: true },
    { label: 'Outstanding balance', value: 'MMK 8.7M', change: '-4.2%', icon: WalletCards, positive: true },
    { label: 'Successful payments', value: '2,846', change: '+8.4%', icon: CreditCard, positive: true },
    { label: 'Refunds issued', value: 'MMK 1.1M', change: '+2.1%', icon: Receipt, positive: false },
];

const trend = [
    { month: 'Apr', value: 58 },
    { month: 'May', value: 72 },
    { month: 'Jun', value: 64 },
    { month: 'Jul', value: 83 },
    { month: 'Aug', value: 76 },
    { month: 'Sep', value: 92 },
];

const methods = [
    { name: 'Mobile wallet', amount: 'MMK 21.4M', percentage: 44, color: 'bg-primary' },
    { name: 'Bank transfer', amount: 'MMK 15.8M', percentage: 33, color: 'bg-success' },
    { name: 'Top-up cards', amount: 'MMK 7.2M', percentage: 15, color: 'bg-warning' },
    { name: 'Cash collection', amount: 'MMK 3.8M', percentage: 8, color: 'bg-info' },
];

const transactions = [
    {
        id: 'INV-10482',
        customer: 'Aung Min Oo',
        method: 'Mobile wallet',
        amount: 'MMK 85,000',
        status: 'Paid',
        date: 'Today, 10:42 AM',
    },
    {
        id: 'INV-10481',
        customer: 'Su Su Hlaing',
        method: 'Bank transfer',
        amount: 'MMK 120,000',
        status: 'Paid',
        date: 'Today, 09:18 AM',
    },
    {
        id: 'INV-10480',
        customer: 'Kyaw Zin',
        method: 'Top-up card',
        amount: 'MMK 50,000',
        status: 'Pending',
        date: 'Yesterday, 04:36 PM',
    },
    {
        id: 'INV-10479',
        customer: 'Mya Thiri',
        method: 'Mobile wallet',
        amount: 'MMK 95,000',
        status: 'Refunded',
        date: 'Yesterday, 02:11 PM',
    },
];

export default function BillingReportsIndex() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('menu.billing_reports')} />

            <PageContent className="gap-3 lg:gap-3.5">
                <PageHeader title={t('menu.billing_reports')} description={t('menu.billing_reports_description')} />

                <div className="flex items-center justify-between gap-3">
                    <Link
                        href="/reports"
                        className="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-primary"
                    >
                        <ArrowLeft className="size-4" />
                        Reports & Analytics
                    </Link>
                    <Badge variant="outline">September 2026</Badge>
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {summary.map(({ label, value, change, icon: Icon, positive }) => (
                        <Card key={label} className="gap-3 py-4">
                            <CardContent className="flex items-start justify-between">
                                <div>
                                    <p className="text-[12px] text-muted-foreground">{label}</p>
                                    <p className="mt-2 font-heading text-xl font-semibold tracking-tight">{value}</p>
                                    <span
                                        className={`mt-2 inline-flex items-center gap-1 text-[12px] ${positive ? 'text-success' : 'text-danger'}`}
                                    >
                                        {positive ? (
                                            <ArrowUpRight className="size-3.5" />
                                        ) : (
                                            <ArrowDownRight className="size-3.5" />
                                        )}
                                        {change} vs last month
                                    </span>
                                </div>
                                <span className="flex size-9 items-center justify-center rounded-md bg-primary/10 text-primary">
                                    <Icon className="size-4.5" />
                                </span>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid grid-cols-1 gap-4 xl:grid-cols-[1.45fr_1fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Collection trend</CardTitle>
                            <CardDescription>Monthly successful payment volume</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex h-56 items-end gap-3 border-b border-border px-2 pb-0 pt-6 sm:gap-6">
                                {trend.map((item) => (
                                    <div
                                        key={item.month}
                                        className="flex h-full flex-1 flex-col items-center justify-end gap-2"
                                    >
                                        <span className="text-[11px] text-muted-foreground">{item.value}%</span>
                                        <div
                                            className="w-full max-w-12 rounded-t-md bg-primary/15"
                                            style={{ height: `${item.value}%` }}
                                        >
                                            <div className="h-full rounded-t-md bg-primary transition-all hover:bg-primary/80" />
                                        </div>
                                        <span className="pb-2 text-[11px] text-muted-foreground">{item.month}</span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Payment methods</CardTitle>
                            <CardDescription>Share of collected revenue</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {methods.map((method) => (
                                <div key={method.name}>
                                    <div className="mb-1.5 flex items-center justify-between gap-3 text-[12px]">
                                        <span className="text-muted-foreground">{method.name}</span>
                                        <span className="font-medium">{method.amount}</span>
                                    </div>
                                    <div className="h-2 overflow-hidden rounded-full bg-muted">
                                        <div
                                            className={`h-full rounded-full ${method.color}`}
                                            style={{ width: `${method.percentage}%` }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <div>
                            <CardTitle>Recent transactions</CardTitle>
                            <CardDescription>Latest payment activity across all channels</CardDescription>
                        </div>
                        <Link
                            href="/billing/transactions"
                            className="text-[12px] font-medium text-primary hover:underline"
                        >
                            View all
                        </Link>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full min-w-[680px] text-left text-[12px]">
                            <thead className="border-b border-border text-muted-foreground">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Invoice</th>
                                    <th className="px-3 py-2 font-medium">Customer</th>
                                    <th className="px-3 py-2 font-medium">Method</th>
                                    <th className="px-3 py-2 font-medium">Amount</th>
                                    <th className="px-3 py-2 font-medium">Status</th>
                                    <th className="px-3 py-2 font-medium">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                {transactions.map((transaction) => (
                                    <tr
                                        key={transaction.id}
                                        className="border-b border-border/70 last:border-0 hover:bg-muted/40"
                                    >
                                        <td className="px-3 py-3 font-medium">{transaction.id}</td>
                                        <td className="px-3 py-3">{transaction.customer}</td>
                                        <td className="px-3 py-3 text-muted-foreground">{transaction.method}</td>
                                        <td className="px-3 py-3 font-medium">{transaction.amount}</td>
                                        <td className="px-3 py-3">
                                            <Badge
                                                variant={
                                                    transaction.status === 'Paid'
                                                        ? 'success'
                                                        : transaction.status === 'Pending'
                                                          ? 'warning'
                                                          : 'secondary'
                                                }
                                            >
                                                {transaction.status}
                                            </Badge>
                                        </td>
                                        <td className="px-3 py-3 text-muted-foreground">{transaction.date}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </PageContent>
        </>
    );
}
