export type AgentFormValues = {
    name: string;
    address: string;
};

type Translate = (key: string) => string;

export function validateAgentField(
    field: keyof AgentFormValues,
    data: AgentFormValues,
    t: Translate,
): string | undefined {
    const value = data[field].trim();

    if (value === '') {
        return t(`top_up_cards.validation.agent_${field}_required`);
    }

    if (field === 'name' && value.length > 50) {
        return t('top_up_cards.validation.agent_name_max');
    }

    if (field === 'address' && value.length > 255) {
        return t('top_up_cards.validation.agent_address_max');
    }

    return undefined;
}

export function validateAgent(data: AgentFormValues, t: Translate): Partial<Record<keyof AgentFormValues, string>> {
    const errors: Partial<Record<keyof AgentFormValues, string>> = {};

    (Object.keys(data) as (keyof AgentFormValues)[]).forEach((field) => {
        const error = validateAgentField(field, data, t);

        if (error) {
            errors[field] = error;
        }
    });

    return errors;
}