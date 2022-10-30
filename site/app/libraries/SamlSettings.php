<?php

namespace app\libraries;

use OneLogin\Saml2\IdPMetadataParser;

class SamlSettings {
    public static function getSettings(Core $core): array {
        $path = FileUtils::joinPaths($core->getConfig()->getConfigPath(), 'saml', 'idp_metadata.xml');
        return [
            'strict' => true,
            'sp' => [
                'entityId' => $core->buildUrl(),
                'assertionConsumerService' => [
                    'url' => $core->buildUrl(['authentication', 'check_login'])
                ]
            ],
            'idp' => IdPMetadataParser::parseFileXML($path)['idp'],
            'security' => [
                'authnRequestsSigned' => true,
                'wantMessagesSigned' => true,
                'wantAssertionsSigned' => true
            ]
        ];
    }
}
