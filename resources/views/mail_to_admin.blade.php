<!--@extends('layouts.app')-->

@section('content')
    <style type="text/css" media="all">
        sup { font-size: 100% !important; }
    </style>
    <style type="text/css" media="screen">
        /* Linked Styles */
        body { padding:0 !important; margin:0 !important; display:block !important; min-width:100% !important; width:100% !important; background:#1d1d1d; -webkit-text-size-adjust:none }
        a { color:#ffffff; text-decoration:none }
        p { padding:0 !important; margin:0 !important }
        img { -ms-interpolation-mode: bicubic; /* Allow smoother rendering of resized image in Internet Explorer */ }
        .mcnPreviewText { display: none !important; }

        /* Mobile styles */
        @media only screen and (max-device-width: 480px), only screen and (max-width: 480px) {
            .mobile-shell { width: 100% !important; min-width: 100% !important; }

            .m-center { text-align: center !important; }

            .center { margin: 0 auto !important; }
            .td { width: 100% !important; min-width: 100% !important; }
            .m-br-15 { height: 15px !important; }
            .m-td,
            .m-hide { display: none !important; width: 0 !important; height: 0 !important; font-size: 0 !important; line-height: 0 !important; min-height: 0 !important; }

            .m-block { display: block !important; }
            .m-auto { height: auto !important; }

            .fluid-img img { width: 100% !important; max-width: 100% !important; height: auto !important; }
            .bg { -webkit-background-size: cover !important; background-size: cover !important; background-repeat: none !important; background-position: center 0 !important; }

            .p20-15 { padding: 20px 15px !important; }
            .p30-15 { padding: 30px 15px !important; }
            .p30-15-0 { padding: 30px 15px 0px 15px !important; }
            .pb-30 { padding-bottom: 30px !important; }
            .pb-30-0 { padding: 30px 0px !important; }

            .nopt { padding-top: 0px !important; }
            .nobb { border-bottom: none !important; }
            .nop { padding: 0 !important; }
            .content { padding: 30px 15px !important; }
            .bt150 { border-top: 30px solid #ffffff !important; }
            .entry { padding-bottom: 30px !important; }
            .pb60m { padding-bottom: 0px !important; }

            .separator { padding-top: 30px !important; }
            .box { padding: 30px 15px !important; }
            .box2 { padding: 30px 15px !important; }
            .pb60 { padding-bottom: 30px !important; }

            .h2 { font-size: 44px !important; line-height: 48px !important; }
            .title { font-size: 24px !important; line-height: 28px !important; }
            .m-list { font-size: 18px !important; line-height: 22px !important; }
            .text-footer { text-align: center !important; }

            .column,
            .column-top,
            .column-dir,
            .column-empty,
            .column-empty2,
            .column-bottom,
            .column-dir-top,
            .column-dir-bottom { float: left !important; width: 100% !important; display: block !important; }

            .column-empty { padding-bottom: 10px !important; }
            .column-empty2 { padding-bottom: 30px !important; }
            .content-spacing { width: 15px !important; }
        }
    </style>

    {{--<p style="color:black;">Vous venez de créer votre compte DRAT. </p><br>
    <a href="https://draht.team-solutions.net/#/verify/{{$ref_user}}" style="background-color: #4CAF50; border: none;color: white;
  padding: 15px 32px;  text-align: center;  text-decoration: none;  display: inline-block; font-size: 16px;">Cliquez ici pour activer votre compte</a>
    <p>Cordialement.</p> <br>
    <p style="color:black; font-weight:bold; size:14px;">L'Association Team@Solutions.</p>--}}
    <div>
        <table width="100%" border="0" cellspacing="0" cellpadding="0">
            <tbody><tr>
                <td class="content-spacing" width="30" style="font-size:0pt; line-height:0pt; text-align:left;"></td>
                <td style="padding: 40px 0px;" class="p30-0">
                    <table width="100%" border="0" cellspacing="0" cellpadding="0">
                        <tbody><tr>
                            <td class="box1" bgcolor="#ffffff" align="center" style="padding:45px 30px;">
                                <table border="0" cellspacing="0" cellpadding="0">
                                    <tbody><tr>
                                        <td class="h5-2 center pb20" style="color:#000000; font-family:'Playfair Display', Georgia, serif; font-size:26px; line-height:30px; text-transform:uppercase; text-align:center; padding-bottom:20px;"><multiline>
                                            {{$nom}}{{$prenom}} </multiline></td>
                                    </tr>
                                    <tr>
                                        <td class="h2 center pb20" style="color:#000000; font-family:'Playfair Display', Georgia, serif; font-size:50px; line-height:54px; font-weight:bold; text-transform:uppercase; text-align:center; padding-bottom:20px;"><multiline>Message</multiline></td>
                                    </tr>
                                    <tr>
                                        <td class="text4 center pb30" style="color:#979797; font-family:'Raleway', Arial,sans-serif; font-size:20px; line-height:24px; text-align:center; padding-bottom:30px;"><multiline>{{--{{$datas['message']}}--}}</multiline></td>
                                    </tr>
                                    {{--<!-- Button -->
                                    <tr>
                                        <td align="center">
                                            <table width="140" border="0" cellspacing="0" cellpadding="0">
                                                <tbody><tr>
                                                    <td class="text-button black-button" style="font-family:'Raleway', Arial, sans-serif; font-size:12px; line-height:16px; text-align:center; text-transform:uppercase; border:2px solid #ffffff; padding:12px 20px; background:#000000; color:#ffffff;"><multiline><a href="#" target="_blank" class="link-white" style="color:#ffffff; text-decoration:none;"><span class="link-white" style="color:#ffffff; text-decoration:none;">shop now</span></a></multiline></td>
                                                </tr>
                                                </tbody></table>
                                        </td>
                                    </tr>
                                    <!-- END Button -->--}}
                                    </tbody></table>
                            </td>
                        </tr>
                        </tbody></table>
                </td>
                <td class="content-spacing" width="30" style="font-size:0pt; line-height:0pt; text-align:left;"></td>
            </tr>
            </tbody></table>
    </div>
@endsection
