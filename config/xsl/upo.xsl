<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:output method="html" encoding="UTF-8" omit-xml-declaration="yes"/>
    <xsl:strip-space elements="*"/>

    <!-- Helpers to resolve namespaced elements by local-name() -->
    <xsl:variable name="UPO" select="/*[local-name()='UPO']"/>

    <xsl:template match="/">
        <html>
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
                <title>UPO – Urzędowe Poświadczenie Odbioru</title>
                <style type="text/css">
                    body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; color: #111; font-size: 12px; margin: 16px; }
                    h1 { font-size: 18px; margin: 0 0 12px 0; }
                    .muted { color: #666; }
                    .section { border: 1px solid #ddd; padding: 10px 12px; margin-bottom: 10px; border-radius: 4px; }
                    table { border-collapse: collapse; width: 100%; }
                    th, td { text-align: left; vertical-align: top; border: 1px solid #e5e7eb; padding: 6px 8px; }
                    th { width: 220px; background: #f9fafb; color: #374151; }
                    code { font-family: DejaVu Sans Mono, Consolas, monospace; font-size: 11px; }
                </style>
            </head>
            <body>
                <h1>Urzędowe Poświadczenie Odbioru (UPO)</h1>
                <div class="section">
                    <table>
                        <tr>
                            <th>Numer KSeF</th>
                            <td><strong><xsl:value-of select="$UPO/*[local-name()='KSeFNumber']"/></strong></td>
                        </tr>
                        <tr>
                            <th>Numer referencyjny sesji</th>
                            <td><xsl:value-of select="$UPO/*[local-name()='ReferenceNumber']"/></td>
                        </tr>
                        <tr>
                            <th>Znacznik czasu</th>
                            <td><xsl:value-of select="$UPO/*[local-name()='Timestamp']"/></td>
                        </tr>
                        <tr>
                            <th>Identyfikator przetwarzania</th>
                            <td><xsl:value-of select="$UPO/*[local-name()='ProcessingIdentifier']"/></td>
                        </tr>
                        <tr>
                            <th>Hash (algorytm)</th>
                            <td><xsl:value-of select="$UPO/*[local-name()='Hash']/*[local-name()='Algorithm']"/></td>
                        </tr>
                        <tr>
                            <th>Hash (wartość)</th>
                            <td><code><xsl:value-of select="$UPO/*[local-name()='Hash']/*[local-name()='Value']"/></code></td>
                        </tr>
                    </table>
                </div>
            </body>
        </html>
    </xsl:template>
</xsl:stylesheet>
