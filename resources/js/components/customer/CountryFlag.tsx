import type { PhoneCountry } from '@/lib/phone';
import { cn } from '@/lib/utils';

type CountryFlagProps = {
    country: PhoneCountry;
    className?: string;
    title?: string;
};

const FLAG_LABEL: Record<Exclude<PhoneCountry, 'unknown'>, string> = {
    mm: 'Myanmar',
    th: 'Thailand',
    cn: 'China',
};

/** Small rounded-square flag badge used in tables and phone fields. */
export function CountryFlag({ country, className, title }: CountryFlagProps) {
    if (country === 'unknown') {
        return null;
    }

    return (
        <span
            className={cn(
                'relative inline-flex h-4 w-5 shrink-0 overflow-hidden rounded-[4px] bg-muted shadow-[inset_0_0_0_1px_rgba(15,23,42,0.12)]',
                className,
            )}
            title={title ?? FLAG_LABEL[country]}
            aria-hidden
        >
            <img
                src={`/images/flags/${country}.svg`}
                alt=""
                className="absolute inset-0 size-full object-cover"
                loading="lazy"
                decoding="async"
            />
        </span>
    );
}
