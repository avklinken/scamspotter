<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\MicrosoftIdentityService;
use App\Support\Env;
use App\Http\Request;

final class OutlookManifestController extends Controller
{
    public function manifest(Request $request): void
    {
        $version = '1.0.6.0';
        $baseUrl = rtrim(url(''), '/');
        $clientId = trim((string) Env::get('MICROSOFT_ENTRA_CLIENT_ID', ''));
        $resource = trim((string) Env::get('MICROSOFT_ENTRA_RESOURCE', ''));
        $sso = MicrosoftIdentityService::enabled();
        $identityApi = $sso ? '<bt:Set Name="IdentityAPI" MinVersion="1.3" />' : '';
        $webApplicationInfo = $sso ? '<WebApplicationInfo><Id>' . e($clientId) . '</Id><Resource>' . e($resource) . '</Resource><Scopes><Scope>openid</Scope><Scope>profile</Scope></Scopes></WebApplicationInfo>' : '';
        $manifest = '<?xml version="1.0" encoding="UTF-8"?>' .
            '<OfficeApp xmlns="http://schemas.microsoft.com/office/appforoffice/1.1" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:bt="http://schemas.microsoft.com/office/officeappbasictypes/1.0" xmlns:mailappor="http://schemas.microsoft.com/office/mailappversion" xsi:type="MailApp">' .
            '<Id>4c73eb9b-3211-4c4b-9bb8-7c6e8cb4d8e9</Id><Version>' . $version . '</Version><ProviderName>ScamSpotter.nl</ProviderName><DefaultLocale>nl-NL</DefaultLocale>' .
            '<DisplayName DefaultValue="ScamSpotter Business" /><Description DefaultValue="Legt uit welke scamwerkwijze achter verdachte zakelijke communicatie zit." />' .
            '<IconUrl DefaultValue="' . e($baseUrl . '/assets/icons/outlook-icon-64.png') . '" /><HighResolutionIconUrl DefaultValue="' . e($baseUrl . '/assets/icons/outlook-icon-128.png') . '" />' .
            '<SupportUrl DefaultValue="' . e($baseUrl . '/contact') . '" /><AppDomains><AppDomain>' . e($baseUrl) . '</AppDomain></AppDomains><Hosts><Host Name="Mailbox" /></Hosts>' .
            '<Requirements><Sets><Set Name="Mailbox" MinVersion="1.5" /></Sets></Requirements>' .
            '<FormSettings><Form xsi:type="ItemRead"><DesktopSettings><SourceLocation DefaultValue="' . e($baseUrl . '/integrations/outlook/taskpane.html?v=' . $version) . '" /><RequestedHeight>450</RequestedHeight></DesktopSettings></Form></FormSettings>' .
            '<Permissions>ReadItem</Permissions><Rule xsi:type="RuleCollection" Mode="And"><Rule xsi:type="ItemIs" ItemType="Message" FormType="Read" /></Rule><DisableEntityHighlighting>true</DisableEntityHighlighting>' .
            '<VersionOverrides xmlns="http://schemas.microsoft.com/office/mailappversionoverrides" xsi:type="VersionOverridesV1_0"><Requirements><bt:Sets DefaultMinVersion="1.5"><bt:Set Name="Mailbox" />' . $identityApi . '</bt:Sets></Requirements>' .
            '<Hosts><Host xsi:type="MailHost"><DesktopFormFactor><ExtensionPoint xsi:type="MessageReadCommandSurface"><OfficeTab id="TabDefault"><Group id="ScamSpotter.MessageReadGroup"><Label resid="GroupLabel" /><Control xsi:type="Button" id="ScamSpotter.CheckButton"><Label resid="CheckButtonLabel" /><Supertip><Title resid="CheckButtonLabel" /><Description resid="CheckButtonTip" /></Supertip><Icon><bt:Image size="16" resid="Icon.16x16" /><bt:Image size="32" resid="Icon.32x32" /><bt:Image size="80" resid="Icon.80x80" /></Icon><Action xsi:type="ShowTaskpane"><SourceLocation resid="Taskpane.Url" /></Action></Control></Group></OfficeTab></ExtensionPoint></DesktopFormFactor></Host></Hosts>' .
            '<Resources><bt:Images><bt:Image id="Icon.16x16" DefaultValue="' . e($baseUrl . '/assets/icons/outlook-icon-16.png') . '" /><bt:Image id="Icon.32x32" DefaultValue="' . e($baseUrl . '/assets/icons/outlook-icon-32.png') . '" /><bt:Image id="Icon.80x80" DefaultValue="' . e($baseUrl . '/assets/icons/outlook-icon-80.png') . '" /></bt:Images><bt:Urls><bt:Url id="Taskpane.Url" DefaultValue="' . e($baseUrl . '/integrations/outlook/taskpane.html?v=' . $version) . '" /></bt:Urls><bt:ShortStrings><bt:String id="GroupLabel" DefaultValue="ScamSpotter" /><bt:String id="CheckButtonLabel" DefaultValue="Controleer met ScamSpotter" /></bt:ShortStrings><bt:LongStrings><bt:String id="CheckButtonTip" DefaultValue="Leg uit welke scamwerkwijze achter dit bericht kan zitten." /></bt:LongStrings></Resources>' .
            $webApplicationInfo . '</VersionOverrides></OfficeApp>';

        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="scamspotter-outlook-manifest.xml"');
        header('Cache-Control: no-store, max-age=0');
        echo $manifest;
    }
}
