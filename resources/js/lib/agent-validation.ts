export type AgentFormValues = {
    name: string;
    cd: number | '';
    address: string;
};

type Translate = (key: string) => string;

export function validateAgentCdUnique(
    cd: AgentFormValues['cd'],
    agentCds: number[],
    currentCd: number | undefined,
    t: Translate,
): string | undefined {
    if (cd !== '' && agentCds.includes(cd) && cd !== currentCd) {
        return t('top_up_cards.validation.agent_cd_unique');
    }

    return undefined;
}

export function validateAgentNameUnique(
    name: string,
    agentNames: string[],
    currentName: string | undefined,
    t: Translate,
): string | undefined {
    const normalizedName = name.trim();

    if (normalizedName !== '' && agentNames.includes(normalizedName) && normalizedName !== currentName) {
        return t('top_up_cards.validation.agent_name_unique');
    }

    return undefined;
}

export function validateAgentField(
    field: keyof AgentFormValues,
    data: AgentFormValues,
    t: Translate,
): string | undefined {
    const value = field === 'cd' ? String(data.cd) : String(data[field]).trim();

    if (value === '') {
        return t(`top_up_cards.validation.agent_${field}_required`);
    }

    if (field === 'name' && value.length > 50) {
        return t('top_up_cards.validation.agent_name_max');
    }

    if (field === 'cd' && (!/^\d{2}$/.test(value) || Number(value) > 99)) {
        return t('top_up_cards.validation.agent_cd_invalid');
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