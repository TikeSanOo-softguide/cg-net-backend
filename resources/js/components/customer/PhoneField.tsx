import { CountryFlag } from '@/components/customer/CountryFlag';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { composePhone, parsePhone, PHONE_COUNTRY_OPTIONS, type PhoneCountry } from '@/lib/phone';
import { cn } from '@/lib/utils';

type PhoneFieldProps = {
    id: string;
    value: string;
    onChange: (phone: string) => void;
    invalid?: boolean;
    required?: boolean;
    className?: string;
};

export function PhoneField({ id, value, onChange, invalid = false, required = false, className }: PhoneFieldProps) {
    const parsed = parsePhone(value);
    const country: PhoneCountry = parsed.country === 'unknown' ? 'mm' : parsed.country;
    const local = parsed.country === 'unknown' && value.trim() === '' ? '' : parsed.local;
    const selected = PHONE_COUNTRY_OPTIONS.find((option) => option.country === country) ?? PHONE_COUNTRY_OPTIONS[0];

    return (
        <div className={cn('flex min-w-0 items-stretch gap-2', className)}>
            <Select
                value={country}
                onValueChange={(next) => onChange(composePhone(next as PhoneCountry, local))}
            >
                <SelectTrigger
                    id={`${id}-country`}
                    className="h-10 w-[8.25rem] shrink-0"
                    aria-invalid={invalid}
                >
                    <SelectValue>
                        <span className="inline-flex items-center gap-2">
                            <CountryFlag country={selected.country} />
                            <span className="tabular-nums">+{selected.dial}</span>
                        </span>
                    </SelectValue>
                </SelectTrigger>
                <SelectContent>
                    {PHONE_COUNTRY_OPTIONS.map((option) => (
                        <SelectItem key={option.country} value={option.country}>
                            <span className="inline-flex items-center gap-2">
                                <CountryFlag country={option.country} />
                                <span className="tabular-nums">+{option.dial}</span>
                                <span className="text-muted-foreground">{option.label}</span>
                            </span>
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Input
                id={id}
                type="tel"
                inputMode="numeric"
                className="min-w-0 flex-1"
                value={local}
                required={required}
                aria-invalid={invalid}
                placeholder="97000000"
                onChange={(event) => onChange(composePhone(country, event.target.value))}
            />
        </div>
    );
}
