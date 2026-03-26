<?php 
$nextMonth = [
		'01' => "Lutego",
		'02' => "Marca",
		'03' => "Kwietnia",
		'04' => "Maja",
		'05' => "Czerwca",
		'06' => "Lipca",
		'07' => "Sierpnia",
		'08' => "Września",
		'09' => "Października",
		'10' => "Listopada",
		'11' => "Grudnia",
        '12' => "Stycznia"
];
// Utworzyliśmy dla ".(($prettyName['gender'] == "M")? "Pana": "Pani")." konto w naszym systemie."
	if(is_array($clientName)){
		$welcomeMsg = (($clientName['gender'] == "M")? "Panie": "Pani");
		$welcomeMsg = $welcomeMsg." <strong>".$clientName['dictionary_vocative']."</strong>";
		$gender = (($clientName['gender'] == "M")? "Pan": "Pani");
	}else{
		$welcomeMsg = $clientName;
		$gender = "Pan";
	}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<meta name="viewport" content="initial-scale=1, maximum-scale=1">
	<meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
	<meta name='format-detection' content='telephone=no'>
    <!-- Web Font / @font-face : BEGIN -->

    <!-- Desktop Outlook chokes on web font references and defaults to Times New Roman, so we force a safe fallback font. -->
    <!--[if mso]>
        <style>
            * {
                font-family: sans-serif !important;
            }
        </style>
    <![endif]-->

    <!-- All other clients get the webfont reference; some will render the font and others will silently fail to the fallbacks. More on that here: http://stylecampaign.com/blog/2015/02/webfont-support-in-email/ -->
    <!--[if !mso]><!-->
        <!-- insert web font reference, eg: <link href="https://fonts.googleapis.com/css?family=Alegreya+Sans:400,700&display=swap&subset=latin-ext" rel="stylesheet"> -->
    <!--<![endif]-->
	<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700|Roboto:300,400,500,600,700">
	<!-- Web Font / @font-face : END -->
		<!-- CSS Reset -->
	<style type="text/css">
		* {line-height: 100%;}
		html, body {width:100% !important; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; margin:0; padding:0;font-family: 'Poppins', sans-serif;}
		#outlook a {padding:0;}
		.ExternalClass {width:100%;}
		.ExternalClass, .ExternalClass p, .ExternalClass span, .ExternalClass font, .ExternalClass td, .ExternalClass div {line-height: 100%;}
		strong { font-weight: bold; }
        /* Beware: It can remove the padding / margin and add a background color to the compose a reply window. */


        /* What it does: Stops email clients resizing small text. */
        * {
            -ms-text-size-adjust: 100%;
            -webkit-text-size-adjust: 100%;
        }

        /* What is does: Centers email on Android 4.4 */
        div[style*="margin: 16px 0"] {
            margin:0 !important;
        }

        /* What it does: Stops Outlook from adding extra spacing to tables. */
        table,
        td {
            mso-table-lspace: 0pt !important;
            mso-table-rspace: 0pt !important;
        }

        /* What it does: Uses a better rendering method when resizing images in IE. */
        img {
            -ms-interpolation-mode:bicubic;
        }

        /* What it does: A work-around for iOS meddling in triggered links. */
        *[x-apple-data-detectors] {
            color: inherit !important;
            text-decoration: none !important;
        }

        /* What it does: A work-around for Gmail meddling in triggered links. */
        .x-gmail-data-detectors,
        .x-gmail-data-detectors *,
        .aBn {
            border-bottom: 0 !important;
            cursor: default !important;
        }

        /* What it does: Prevents Gmail from displaying an download button on large, non-linked images. */
        .a6S {
	        display: none !important;
	        opacity: 0.01 !important;
        }
        /* If the above doesn't work, add a .g-img class to any image in question. */
        img.g-img + div {
	        display:none !important;
	   	}

        /* What it does: Prevents underlining the button text in Windows 10 */
        .button-link {
            text-decoration: none !important;
        }

        /* What it does: Removes right gutter in Gmail iOS app: https://github.com/TedGoas/Cerberus/issues/89  */
        /* Create one of these media queries for each additional viewport size you'd like to fix */
        /* Thanks to Eric Lepetit @ericlepetitsf) for help troubleshooting */
        @media only screen and (min-device-width: 375px) and (max-device-width: 413px) { /* iPhone 6 and 6+ */
            .email-container {
                min-width: 375px !important;
            }
        }
        /* What it does: Hover styles for buttons */
        .button {
        	display: inline-block;
		    padding: 6px 12px;
		    margin-bottom: 0;
		    font-size: 14px;
		    font-weight: 400;
		    line-height: 1.42857143;
		    text-align: center;
		    white-space: nowrap;
		    vertical-align: middle;
		    cursor: pointer;
		    -webkit-user-select: none;
		    -moz-user-select: none;
		    -ms-user-select: none;
		    user-select: none;
		    background-image: none;
		    border: 1px solid transparent;
		    border-radius: 4px;
            transition: all 100ms ease-in;
        }
        .button:hover{
            background: #508c37 !important;
			color:#fff !important;
        }
        strong {
        	color: #6fc14b;
        }
		ul li{
			line-height: 1.4em;
			margin-bottom: .7em;
		}
	</style>
	<title></title>
</head>
<body style="-webkit-text-size-adjust:none; background:#e5e5e5; padding:0;margin:0">
<div style="background-color: #e5e5e5">
<!-- WRAPPER-->
<table  width="100%" style="table-layout: fixed">
	<tr>
		<td>
			<!-- BODY -->
			<table align="center" bgcolor="#e5e5e5" border="0" cellpadding="0" cellspacing="0" width="600" style="width:600px; margin:0 auto">
				<!-- HEADER -->
				<tr>
					<td width="600" style="width:600px">
						<table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="width:600px">
							<!-- PREHEADER -->
							<tr>
								<td height="1" bgcolor="#e5e5e5" style="color:#e5e5e5; font-size: 1px; line-height: 1px">
									<div style="color:#e5e5e5; font-size: 1px; line-height: 1px;display:none">Partner S.C.</div>
								</td>
							</tr>
							<!-- END OF PREHEADER -->
							<tr>
								<td height="20" style="height:20px"></td>
							</tr>
						</table>
					</td>
				</tr>
				<!-- END OF HEADER -->
				<tr>
					<td width="600" bgcolor="#ffffff" align="center" style="width:600px;">
						<br>
						<img src="/assets/media/logos/PartnerLogo.png" border="0" alt="Partner S.C." moz-do-not-send="true" style="text-align: center; vertical-align:middle; border:none;display:block">
					</td>
				</tr>
				<!-- MAIN CONTENT -->
				<tr>
					<td width="600" style="width:600px">
						<table align="center" bgcolor="#ffffff" border="0" cellpadding="0" cellspacing="0" width="600" style="width:600px">
							<!-- MARGIN ------------------ -->
							<tr>
								<td colspan="3" width="600" height="30" style="width:600px; height:30px;  line-height: 0px; font-size:0px;">&nbsp;</td>
							</tr>
							<!-- /MARGIN ----------------- -->
							<!-- HR------------------ -->
							<tr>
								<td bgcolor="#e8e8e8" colspan="3" width="600" height="1" style="width:600px; height:1px; line-height: 0px; font-size:0px;">&nbsp;</td>
							</tr>
							<!-- /HR ----------------- -->
							<!-- MARGIN ------------------ -->
							<tr>
								<td colspan="3" width="600" height="20" style="width:600px; height:20px;  line-height: 0px; font-size:0px;">&nbsp;</td>
							</tr>
							<!-- /MARGIN ----------------- -->
							<tr>
								<td width="40" style="width:40px"></td>
								<td width="520" style="width:520px; color:#191919; font-size:16px; line-height: 1.5em; text-align:left;">
									<?= $welcomeMsg; ?>,
								</td>
								<td width="40" style="width:40px"></td>
							</tr>
							<!-- MARGIN ------------------ -->
							<tr>
								<td colspan="3" width="600" height="15" style="width:600px; height:15px; line-height: 0px; font-size:0px;">&nbsp;</td>
							</tr>
							<!-- /MARGIN ----------------- -->
							<tr>
								<td width="40" style="width:40px"></td>
								<td width="520" style="width:520px;">
									<div style="color:#464646; font-size:16px;  line-height: 1.4em; text-align:justify;">
                                        proszę o niezwłoczne dostarczenie dokumentów księgowych za miesiąc <strong><?= $bookkeepingDetail->bookkeeping_period->name; ?></strong>. 
									</div>
									<div style="padding:10px 0 20px; color:#464646; font-size:16px;  line-height: 1.4em; text-align:left;">
										Pozdrawiam,<br>
										<?= $company->employee->name; ?> <?= $company->employee->surname; ?>
									</div>
									
								</td>
								<td width="40" style="width:40px"></td>
							</tr>
							
						</table>
					</td>
				</tr>
				<tr>
					<td width="600" style="width:600px">
						<table align="center" bgcolor="#ffffff" border="0" cellpadding="0" cellspacing="0" width="600" style="width:600px">
							<!-- HR------------------ -->
							<tr>
								<td bgcolor="#e8e8e8" colspan="3" width="600" height="1" style="width:600px; height:1px; line-height: 0px; font-size:0px;">&nbsp;</td>
							</tr>
							<!-- /HR ----------------- -->
							<!-- MARGIN ------------------ -->
							<tr>
								<td colspan="3" width="600" height="10" style="width:600px; height:10px; line-height: 0px; font-size:0px;"></td>
							</tr>
							<!-- /MARGIN ----------------- -->
							<tr>
								<td width="20" style="width:20px;"></td>
								<td width="560" style="width:560px;">
									<div style="padding:10px 20px 10px; color:#5c5c5c; font-size:16px; line-height: 1.3em; text-align:center; text-transform: uppercase;">
										Infolinia: <a href="tel:801002292" style="color:#6fc14b; text-decoration: none; font-size: 18px;">801 002 292</a><br>pon. - pt.: 7<sup>00</sup> - 15<sup>00</sup>
									</div>
								</td>
								<td width="20" style="width:20px"></td>
							</tr>
							<!-- MARGIN ------------------ -->
							<tr>
								<td colspan="3" width="600" height="10" style="width:600px; height:10px; line-height: 0px; font-size:0px;"></td>
							</tr>
							<!-- /MARGIN ----------------- -->
							<!-- HR------------------ -->
							<tr>
								<td bgcolor="#e8e8e8" colspan="3" width="600" height="1" style="width:600px; height:1px; line-height: 0px; font-size:0px;">&nbsp;</td>
							</tr>
							<!-- /HR ----------------- -->
						</table>
					</td>
				</tr>
				<!-- FOOTER 1 -->
				<tr>
					<td width="600" style="width:600px">
						<table align="center" bgcolor="#6fc14b" border="0" cellpadding="0" cellspacing="0" width="600" style="width:600px; height:10px;">
							<tr>
								<td height="10" style="height:10px; line-height:10px;">&nbsp;</td>
							</tr>
						</table>
					</td>
				</tr>
				<!-- END OF FOOTER 1 -->
				<!-- END OF MAIN CONTENT -->
				<!-- FOOTER 2 -->
				<tr>
					<td width="600" style="width:600px">
						<table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="width:600px">
							<!-- MARGIN ------------------ -->
							<tr>
								<td width="600" height="15" style="width:600px; height:15px; line-height:15px;">&nbsp;</td>
							</tr>
							<!-- /MARGIN ----------------- -->
							<tr>
								<td align="center" style="font-size:12px; color:#5c5c5c; line-height: 1.3em;">
									Biuro Rachunkowe "PARTNER" s.c., 01-402 Warszawa, ul. Ciołka 10
									<br>
									NIP: 527-251-12-37 , REGON: 140584751
								</td>
							</tr>
							<!-- MARGIN ------------------ -->
							<tr>
								<td width="600" height="25" style="width:600px; height:25px; line-height:25px;">&nbsp;</td>
                            </tr>
                            <!-- /MARGIN ----------------- -->
						</table>
					</td>
				</tr>
				<!-- END OF FOOTER 2 -->
			</table>
			<!-- END OF BODY-->
		</td>
	</tr>
</table>
<!-- END OF WRAPPER-->
</div>
</body>
</html>