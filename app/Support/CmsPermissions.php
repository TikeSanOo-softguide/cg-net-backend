<?php

namespace App\Support;

final class CmsPermissions
{
    public const View = 'cms.view';

    public const Create = 'cms.create';

    public const Update = 'cms.update';

    public const Delete = 'cms.delete';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::View,
            self::Create,
            self::Update,
            self::Delete,
        ];
    }
}
