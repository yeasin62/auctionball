<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9">
    <xsl:output method="html" encoding="UTF-8" indent="yes" />

    <xsl:template match="/">
        <html>
            <head>
                <title>AuctionBall Sitemap</title>
                <meta name="viewport" content="width=device-width, initial-scale=1" />
                <style>
                    body {
                        margin: 0;
                        padding: 32px;
                        font-family: Arial, sans-serif;
                        color: #111827;
                        background: #f8fafc;
                    }
                    h1 {
                        margin: 0 0 6px;
                        font-size: 28px;
                    }
                    p {
                        margin: 0 0 24px;
                        color: #64748b;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        background: white;
                        border: 1px solid #e5e7eb;
                    }
                    th, td {
                        padding: 12px 14px;
                        border-bottom: 1px solid #e5e7eb;
                        text-align: left;
                        font-size: 14px;
                        white-space: nowrap;
                    }
                    th {
                        background: #f1f5f9;
                        color: #475569;
                        font-size: 12px;
                        text-transform: uppercase;
                        letter-spacing: .04em;
                    }
                    a {
                        color: #2563eb;
                        text-decoration: none;
                    }
                    a:hover {
                        text-decoration: underline;
                    }
                    .url {
                        width: 100%;
                        white-space: normal;
                        word-break: break-word;
                    }
                </style>
            </head>
            <body>
                <h1>AuctionBall Sitemap</h1>
                <p>
                    <xsl:value-of select="count(s:urlset/s:url)" />
                    public URLs found.
                </p>
                <table>
                    <thead>
                        <tr>
                            <th>URL</th>
                            <th>Last modified</th>
                            <th>Change frequency</th>
                            <th>Priority</th>
                        </tr>
                    </thead>
                    <tbody>
                        <xsl:for-each select="s:urlset/s:url">
                            <tr>
                                <td class="url">
                                    <a href="{s:loc}">
                                        <xsl:value-of select="s:loc" />
                                    </a>
                                </td>
                                <td><xsl:value-of select="s:lastmod" /></td>
                                <td><xsl:value-of select="s:changefreq" /></td>
                                <td><xsl:value-of select="s:priority" /></td>
                            </tr>
                        </xsl:for-each>
                    </tbody>
                </table>
            </body>
        </html>
    </xsl:template>
</xsl:stylesheet>
