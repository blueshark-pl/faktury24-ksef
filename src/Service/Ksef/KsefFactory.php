<?php
declare(strict_types=1);

namespace App\Service\Ksef;

final class KsefFactory
{
    public static function makeMasterService(): N1KsefMasterService
    {
        $cert = new MasterCertProvider();
        $meta = new FileMetaStorage();

        return new N1KsefMasterService($cert, $meta);
    }
}
