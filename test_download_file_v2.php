<?php
/**
 * SPOTTER — Dynamic AQA fetch endpoint for test_download_v3.html
 * GET params: code (e.g. 8464B1H or 84611H), type (qp|ms), year (e.g. Jun18)
 *
 * URL PATTERN (confirmed working, no random hash):
 *   https://filestore.aqa.org.uk/sample-papers-and-mark-schemes/{year}/{month}/AQA-{code}-{seg}-{YEARSUFFIX}.PDF
 * where {seg} = QP always, or MS (2022+) / W-MS (2018-2021) for mark schemes.
 *
 * Place alongside test_download_v3.html in /var/www/html/qspotter/
 */

error_reporting(E_ALL & ~E_DEPRECATED);
set_time_limit(30);

// ── Guessed-pattern fallback — now empty since every year Jun18-25 has a   ─
//    hand-verified table below. Left in place in case a future year needs  ─
//    the pattern before it's individually confirmed.
$YEARS = [];

// ── Combined Science specimen set — confirmed real URLs (not a guessable    ─
//    pattern, hand-verified from AQA's own resource listing). Component
//    number below is 1=BioP1, 2=BioP2, 3=ChemP1, 4=ChemP2, 5=PhysP1, 6=PhysP2
$SPECIMEN_COMBINED = [
    '8464B1H' => ['qp' => 'https://www.aqa.org.uk/files/resources.science.AQA-84641B1H-SQP_PDF/97f6654b8733a2701cba8a9db798f98cdc3a1dbb.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/resources.science.AQA-84641B1H-SMS_PDF/05ac00cd347f18c758afcd70bc2b3a570d56291d.pdf'],
    '8464B1F' => ['qp' => 'https://www.aqa.org.uk/files/resources.science.AQA-84641B1F-SQP-CR_PDF/5ed0c2d076d3c04cc2e01c7ef8315b748d8f1333.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/resources.science.AQA-84641B1F-SMS_PDF/77037ca9cde84149f1174b4909e0f43ff44f75ea.pdf'],
    '8464B2H' => ['qp' => 'https://www.aqa.org.uk/files/resources.science.AQA-84642B2H-SQP-CR_PDF/282181702dbf9c207c25427b6b7b289b5ea821c6.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/resources.science.AQA-84642B2H-SMS_PDF/249582f30c3ff8adc8245b178df4cd57c23a2d40.pdf'],
    '8464B2F' => ['qp' => 'https://www.aqa.org.uk/files/resources.science.AQA-84642B2F-SQP-CR_PDF/d5c216ffd6450aec78300c9166d79dd2537a721f.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/resources.science.AQA-84642B2F-SMS_PDF/c38befe7729fd28d0908164f2a4b5b48109633a5.pdf'],
    '8464C1H' => ['qp' => 'https://www.aqa.org.uk/files/resources.science.AQA-84643C1H-SQP_PDF/2716839f7b6fe83f6680a1a68f5d705a7c7164a8.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/resources.science.AQA-84643C1H-SMS_PDF/1a124f614e967008b76eed4af8b2f81e70c9ac6b.pdf'],
    '8464C1F' => ['qp' => 'https://www.aqa.org.uk/files/resources.science.AQA-84643C1F-SQP_PDF/9425f674c6f7eaa59fcb98f22692447f3dd1c3d2.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/resources.science.AQA-84643C1F-SMS_PDF/066ff879d480308086cbe3cea3b1922ba033189d.pdf'],
    '8464C2H' => ['qp' => 'https://www.aqa.org.uk/files/resources.science.AQA-84644C2H-SQP_PDF/ac346c22d1d8e93d8288d3c17503148c722abfdb.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/resources.science.AQA-84644C2H-SMS_PDF/4d75180f7fe02c8e23d72cc5594136eb6b89ab94.pdf'],
    '8464C2F' => ['qp' => 'https://www.aqa.org.uk/files/resources.science.AQA-84644C2F-SQP_PDF/ee4fe8d887440747fae63609d4801ad81ea749da.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/resources.science.AQA-84644C2F-SMS_PDF/ae3480eed74f5298a6e92b2fb84f773472a817e7.pdf'],
    '8464P1H' => ['qp' => 'https://www.aqa.org.uk/files/resources.science.AQA-84645P1H-SQP-CR_PDF/a894d51fe40d8d6ce631d031e13928e2e74a9d38.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/resources.science.AQA-84645P1H-SMS_PDF/c7a82fd58b00e15df582e64080b1a17852f08b47.pdf'],
    '8464P1F' => ['qp' => 'https://filestore.aqa.org.uk/resources/science/AQA-84645P1F-SQP-CR.PDF', // verified working direct link, replaced a stale hashed URL
                  'ms' => 'https://filestore.aqa.org.uk/resources/science/AQA-84645P1F-SMS.PDF'], // verified working direct link, replaced a stale hashed URL
    '8464P2H' => ['qp' => 'https://www.aqa.org.uk/files/resources.science.AQA-84646P2H-SQP_PDF/97f1d201c9a5539c54c32417177f744cfb45dd82.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/resources.science.AQA-84646P2H-SMS_PDF/0055f8f1d01dad14f4d7ea73bc56fa7dece322a5.pdf'],
    '8464P2F' => ['qp' => 'https://www.aqa.org.uk/files/resources.science.AQA-84646P2F-SQP_PDF/c7e263d8dffeaad477f0523cc0eba410cc309758.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/resources.science.AQA-84646P2F-SMS_PDF/b089c9e56d8872b032a80f0c014e3d45266ff691.pdf'],
];

// ── Triple Science (8461 Bio / 8462 Chem / 8463 Phys) — hand-verified,     ─
//    gathered and cross-checked paper-by-paper (Aug 2026). Each entry notes
//    its source: AQA-direct where available; PMT (physicsandmathstutor.com)
//    or MME (mmerevise.co.uk) for confirmed AQA gaps (mainly Jun18/Jun19,
//    which AQA's own site doesn't publish for several Triple components).
//    Third-party sources are flagged inline below and were only used after
//    AQA's own search + direct outreach (email/phone) came back empty.

$JUN18_TRIPLE = [
    '84611H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-84611H-QP-JUN18_PDF/e8a0009c360109d76a8dd3364a6fbdfe5e681f3a.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-84611H-W-MS-JUN18_PDF/f10840210e33cb201375de228fb6b10028bc2736.pdf'],  // Biology P1H
    '84611F' => ['qp' => 'https://pmt.physicsandmathstutor.com/download/Biology/GCSE/Past-Papers/AQA/Paper-1F/QP/June%202018%20QP.pdf',  // PMT
                 'ms' => 'https://pmt.physicsandmathstutor.com/download/Biology/GCSE/Past-Papers/AQA/Paper-1F/MS/June%202018%20MS.pdf'],  // PMT  // Biology P1F
    '84612H' => ['qp' => 'https://pmt.physicsandmathstutor.com/download/Biology/GCSE/Past-Papers/AQA/Paper-2H/QP/June%202018%20QP.pdf',  // PMT
                 'ms' => 'https://pmt.physicsandmathstutor.com/download/Biology/GCSE/Past-Papers/AQA/Paper-2H/MS/June%202018%20MS.pdf'],  // PMT  // Biology P2H
    '84612F' => ['qp' => 'https://pmt.physicsandmathstutor.com/download/Biology/GCSE/Past-Papers/AQA/Paper-2F/QP/June%202018%20QP.pdf',  // PMT
                 'ms' => 'https://pmt.physicsandmathstutor.com/download/Biology/GCSE/Past-Papers/AQA/Paper-2F/MS/June%202018%20MS.pdf'],  // PMT  // Biology P2F
    '84621H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-84621H-QP-JUN18_PDF/0540606fa2be29d160026552d96f43bb8e1c0a28.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-84621H-W-MS-JUN18_PDF/83bef820f668c960cf134db98157c896a2f01144.pdf'],  // Chemistry P1H
    '84621F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-84621F-QP-JUN18_PDF/880c613e31e7634a9e9b690c8f02627f1acfdde0.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-84621F-W-MS-JUN18_PDF/f1adc0c64c03b74e1e8e113863fab88651ce417b.pdf'],  // Chemistry P1F
    '84622H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-84622H-QP-JUN18_PDF/4e61127707765cfcb4bf2093f1781fc8aa9f6908.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-84622H-W-MS-JUN18_PDF/f30bbcc2483635460aa86adf4d6f0825196a0995.pdf'],  // Chemistry P2H
    '84622F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-84622F-QP-JUN18_PDF/6d744d3cc207d616b859c2c4233e61d042befedb.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-84622F-W-MS-JUN18_PDF/321a000572ecf3edf3fffb6cf9512272f396982f.pdf'],  // Chemistry P2F
    '84631H' => ['qp' => 'https://pmt.physicsandmathstutor.com/download/Physics/GCSE/Past-Papers/AQA/Paper-1H/QP/June%202018%20QP.pdf',  // PMT
                 'ms' => 'https://pmt.physicsandmathstutor.com/download/Physics/GCSE/Past-Papers/AQA/Paper-1H/MS/June%202018%20MS.pdf'],  // PMT  // Physics P1H
    '84631F' => ['qp' => 'https://pmt.physicsandmathstutor.com/download/Physics/GCSE/Past-Papers/AQA/Paper-1F/QP/June%202018%20QP.pdf',  // PMT
                 'ms' => 'https://pmt.physicsandmathstutor.com/download/Physics/GCSE/Past-Papers/AQA/Paper-1F/MS/June%202018%20MS.pdf'],  // PMT  // Physics P1F
    '84632H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-84632H-QP-JUN18_PDF/a26bb85f09fa4a6965ce265fbfe670a57a1154a1.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-84632H-W-MS-JUN18_PDF/847ee349b613095b44bb6fb44b9ae6a7c3bf13e2.pdf'],  // Physics P2H
    '84632F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-84632F-QP-JUN18_PDF/8b94336dbf0036551bfebf0b71b651de40b09ef7.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-84632F-W-MS-JUN18_PDF/1cfbbfa120d11bc9e4daac02afca3d328ff8ea1d.pdf'],  // Physics P2F
];

$JUN19_TRIPLE = [
    '84611H' => ['qp' => 'https://pmt.physicsandmathstutor.com/download/Biology/GCSE/Past-Papers/AQA/Paper-1H/QP/June%202019%20QP.pdf',  // PMT
                 'ms' => 'https://pmt.physicsandmathstutor.com/download/Biology/GCSE/Past-Papers/AQA/Paper-1H/MS/June%202019%20MS.pdf'],  // PMT  // Biology P1H
    '84611F' => ['qp' => 'https://pmt.physicsandmathstutor.com/download/Biology/GCSE/Past-Papers/AQA/Paper-1F/QP/June%202019%20QP.pdf',  // PMT
                 'ms' => 'https://pmt.physicsandmathstutor.com/download/Biology/GCSE/Past-Papers/AQA/Paper-1F/MS/June%202019%20MS.pdf'],  // PMT  // Biology P1F
    '84612H' => ['qp' => 'https://pmt.physicsandmathstutor.com/download/Biology/GCSE/Past-Papers/AQA/Paper-2H/QP/June%202019%20QP.pdf',  // PMT
                 'ms' => 'https://pmt.physicsandmathstutor.com/download/Biology/GCSE/Past-Papers/AQA/Paper-2H/MS/June%202019%20MS.pdf'],  // PMT  // Biology P2H
    '84612F' => ['qp' => 'https://pmt.physicsandmathstutor.com/download/Biology/GCSE/Past-Papers/AQA/Paper-2F/QP/June%202019%20QP.pdf',  // PMT
                 'ms' => 'https://pmt.physicsandmathstutor.com/download/Biology/GCSE/Past-Papers/AQA/Paper-2F/MS/June%202019%20MS.pdf'],  // PMT  // Biology P2F
    '84621H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-84621H-QP-JUN19_PDF/aad8d4f710f17456e2cbe6373a179e7c0c5a0a89.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-84621H-W-MS-JUN19_PDF/6fc482be5d4e2f76610af52c0aa5b56bb8b17cc8.pdf'],  // Chemistry P1H
    '84621F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-84621F-QP-JUN19_PDF/e17cfe67b81614cec36c1fc7b7db6364d24ec40a.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-84621F-W-MS-JUN19_PDF/40184c5d3906b53aa607bbd0ebc2615d3a311dcf.pdf'],  // Chemistry P1F
    '84622H' => ['qp' => 'https://mmerevise.co.uk/app/uploads/2021/09/AQA-GCSE-Chemistry-Higher-Paper-2-QP.pdf',  // MME
                 'ms' => 'https://mmerevise.co.uk/app/uploads/2021/09/AQA-GCSE-Chemistry-Higher-Paper-2-MS.pdf'],  // MME  // Chemistry P2H
    '84622F' => ['qp' => 'https://mmerevise.co.uk/app/uploads/2021/09/AQA-GCSE-Chemistry-Foundation-Paper-2-QP.pdf',  // MME
                 'ms' => 'https://mmerevise.co.uk/app/uploads/2021/09/AQA-GCSE-Chemistry-Foundation-Paper-2-MS.pdf'],  // MME  // Chemistry P2F
    '84631H' => ['qp' => 'https://pmt.physicsandmathstutor.com/download/Physics/GCSE/Past-Papers/AQA/Paper-1H/QP/June%202019%20QP.pdf',  // PMT
                 'ms' => 'https://pmt.physicsandmathstutor.com/download/Physics/GCSE/Past-Papers/AQA/Paper-1H/MS/June%202019%20MS.pdf'],  // PMT  // Physics P1H
    '84631F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-84631F-QP-JUN19_PDF/06ecda03a98a51575bcc6d3d6fb664eee228e946.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-84631F-W-MS-JUN19_PDF/e86b359502e54dd161d6825be999b717e48702aa.pdf'],  // Physics P1F
    '84632H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-84632H-QP-JUN19_PDF/7bff7b5e80ae88684b505ffc2384c53bc74cf7ae.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-84632H-W-MS-JUN19_PDF/a604e16cba875a2bbd6ac98975f1ea817a62e930.pdf'],  // Physics P2H
    '84632F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-84632F-QP-JUN19_PDF/521e4fa647db9fe781879dd335ee9253e0d254c6.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-84632F-W-MS-JUN19_PDF/7988d0b7e389b2355afdff6c73c074c64d085783.pdf'],  // Physics P2F
];

$NOV20_TRIPLE = [
    '84611H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-84611H-QP-NOV20_PDF/6fc7e4f44d60cbb149879a63cba29f6de6f9d21e.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-84611H-W-MS-NOV20_PDF/85cb3afb57ec3f141048dcad467156bf9bf5bc3e.pdf'],  // Biology P1H
    '84611F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-84611F-QP-NOV20_PDF/75dd1b43909fec5f2265dbec4ae496ab2858df10.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-84611F-W-MS-NOV20_PDF/5bdf088ff0cff1b1a4a120f69d43318c73ba3b50.pdf'],  // Biology P1F
    '84612H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-84612H-QP-NOV20_PDF/f0909da2ded82d9e584afb300ff867531e56b9b0.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-84612H-W-MS-NOV20_PDF/5b59a36f7010af934f11f40f6ed976a9fe5c653b.pdf'],  // Biology P2H
    '84612F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-84612F-QP-NOV20_PDF/df33a5c0cd7ab47cc3bcc6cbde5739549fdfaf0e.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-84612F-W-MS-NOV20_PDF/137452f41fd8800a3428cf60ad71e00b844d874e.pdf'],  // Biology P2F
    '84621H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-84621H-QP-NOV20_PDF/768fb3f9fa7502162c256460d9e9e9c99fb7bcb1.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-84621H-W-MS-NOV20_PDF/d451f429ccfed69977a4cd4b8a0892961ab6a379.pdf'],  // Chemistry P1H
    '84621F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-84621F-QP-NOV20_PDF/7301ed30d764df36530d745a6290bb61540b6f21.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-84621F-W-MS-NOV20_PDF/a705105c5fbf7016c143c317d7a36d4cbe615ab5.pdf'],  // Chemistry P1F
    '84622H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-84622H-QP-NOV20_PDF/57256f0761b3371ead0e2360be98ee4529fff6b4.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-84622H-W-MS-NOV20_PDF/ef2abea203a48494cfa2cc1dd10bd94f836a253c.pdf'],  // Chemistry P2H
    '84622F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-84622F-QP-NOV20_PDF/65a28adfe15d46d1de9c2faf1812b4653f3d39ca.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-84622F-W-MS-NOV20_PDF/42c2b587a48279b0f02922c2bf9c48982783e507.pdf'],  // Chemistry P2F
    '84631H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-84631H-QP-NOV20_PDF/1a8bd813ea75c23e805529b24f9e89c31aa371b5.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-84631H-W-MS-NOV20_PDF/bf730f3e5c4a776c966f4a887e5b0da28a4be83d.pdf'],  // Physics P1H
    '84631F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-84631F-QP-NOV20_PDF/1ddd74a0dc6e4f3c98cd2794054334e7406056dd.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-84631F-W-MS-NOV20_PDF/51b55e75d68c3f8680fb88fc499fe1f90c500df4.pdf'],  // Physics P1F
    '84632H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-84632H-QP-NOV20_PDF/86bb907604da4822c0a8e0fa9c2cf8d945c27f2a.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-84632H-W-MS-NOV20_PDF/4b9cb033c63d87e019b27cf8d2bd7fe92b261f99.pdf'],  // Physics P2H
    '84632F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-84632F-QP-NOV20_PDF/72240bd6e43057537ba64e06ee72a6f93fa23ecd.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-84632F-W-MS-NOV20_PDF/e64480b47ead40b57522a5374d186946b0f94bcd.pdf'],  // Physics P2F
];

$NOV21_TRIPLE = [
    '84611H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-84611H-QP-NOV21_PDF/bbfe6ac78c52a384e4e8aa57e24d651f950329d1.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-84611H-MS-NOV21_PDF/88dc2e852403fdaff9a4d39329da9ba0ade197aa.pdf'],  // Biology P1H
    '84611F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-84611F-QP-NOV21_PDF/82d40f82efb5f0843167d25d7a7c89bb652b8fa1.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-84611F-MS-NOV21_PDF/7bebcd31c77c4104b5a5d1ab822c72eb1d1bddbb.pdf'],  // Biology P1F
    '84612H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-84612H-QP-NOV21_PDF/f7a270b8a3a688506da9e52f20eef9bd06112edf.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-84612H-MS-NOV21_PDF/3a226433f7bad5974cd270c2da2dac7d1146949d.pdf'],  // Biology P2H
    '84612F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-84612F-QP-NOV21_PDF/12e85710305ac2956622081277e382615acb9c51.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-84612F-MS-NOV21_PDF/ad65d495a9e981f049d3a2c4b5bc775f4e71e7a1.pdf'],  // Biology P2F
    '84621H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-84621H-QP-NOV21_PDF/79d21fc272a171d5554a86dd9c6187199b2e1c16.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-84621H-MS-NOV21_PDF/f74e0c6ffa1fb2cd2ae859b979f1c860621995aa.pdf'],  // Chemistry P1H
    '84621F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-84621F-QP-NOV21_PDF/d5465a9407ba1205734337929157988bb2000e6a.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-84621F-MS-NOV21_PDF/5368548dd33786dc5c77f5f159752a22b0d78e22.pdf'],  // Chemistry P1F
    '84622H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-84622H-QP-NOV21_PDF/cebf03e179ff1f49ef67b954355b1e8003dd550c.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-84622H-MS-NOV21_PDF/0d5064cbac9a48b0d087f1d6703e8a46ce06496a.pdf'],  // Chemistry P2H
    '84622F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-84622F-QP-NOV21_PDF/9ef159a8327ddbe56ce6ea8de9e9b64707ad8f50.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-84622F-MS-NOV21_PDF/7067a9ebe07fa4309936ec549e8c6d30497a76a8.pdf'],  // Chemistry P2F
    '84631H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-84631H-QP-NOV21_PDF/864c92bd953e37f8f5d087b2c09024cc77615606.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-84631H-MS-NOV21_PDF/0f885e7469fe83e6eb59e7ded9fea529ebbbe8ae.pdf'],  // Physics P1H
    '84631F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-84631F-QP-NOV21_PDF/d153766a1181f43413bfd781ae38986d040f41b5.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-84631F-MS-NOV21_PDF/ac4b87ef93bf64012db51b25ecd23d7e26a03eef.pdf'],  // Physics P1F
    '84632H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-84632H-QP-NOV21_PDF/bb30f0f34dbbbb95f9b0585d36a47c95079c136b.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-84632H-MS-NOV21_PDF/59092fff61a7a5ec44c469a7394c45e9c5a23d40.pdf'],  // Physics P2H
    '84632F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-84632F-QP-NOV21_PDF/56d200c856afc2d41d0a6672fd5abe06ca6468e7.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-84632F-MS-NOV21_PDF/ed0e87cc551e5ed7d456bee48e927c25fced5ec5.pdf'],  // Physics P2F
];

$JUN22_TRIPLE = [
    '84611H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-84611H-QP-JUN22_PDF/9016d570203ac764a4b3716392e9ad0925d0ea8b.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-84611H-MS-JUN22_PDF/a76ee1ede52e47e5ca9885beb719f3ab1ed732d2.pdf'],  // Biology P1H
    '84611F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-84611F-QP-JUN22_PDF/ecaa4fa9ce38d87f90c9511c28ad48e8673f15a4.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-84611F-MS-JUN22_PDF/61c2da17f1e6446addf23bbc832f23119ea2867a.pdf'],  // Biology P1F
    '84612H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-84612H-QP-JUN22_PDF/b3bc256d1193aab692439fd016c7b28d64a37602.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-84612H-MS-JUN22_PDF/3e9a43d3a36cc7480327052dc13fb53064b7012a.pdf'],  // Biology P2H
    '84612F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-84612F-QP-JUN22_PDF/2f8660aa32c19e622ebcabbe243dd8d2c189b5ff.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-84612F-MS-JUN22_PDF/16d04512995d15dca81a53fd46b148a1d72c4a26.pdf'],  // Biology P2F
    '84621H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-84621H-QP-JUN22_PDF/e712647aac2b2bc2acc712e346dea258836f3791.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-84621H-MS-JUN22_PDF/4702af39dffad4119cd215815e3fd9c8e73dfbd2.pdf'],  // Chemistry P1H
    '84621F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-84621F-QP-JUN22_PDF/3baef3cf3687454106485d1f01419e06ce98179d.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-84621F-MS-JUN22_PDF/2f9ca2e1a220359de0436e351dcb31fde041ab6d.pdf'],  // Chemistry P1F
    '84622H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-84622H-QP-JUN22_PDF/e2de630ac00558366d6cd35ddf3112e3c03e0552.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-84622H-MS-JUN22_PDF/659f064ce2f39e309b767213082035ef593e93de.pdf'],  // Chemistry P2H
    '84622F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-84622F-QP-JUN22_PDF/bf334193f4bdb18d66ba8f43b98e9543e562e3d7.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-84622F-MS-JUN22_PDF/72f3cee99a5cf37966db1dfa2a1e4d9057f902ff.pdf'],  // Chemistry P2F
    '84631H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-84631H-QP-JUN22_PDF/497cb7d82fc035f1bc44219edd9f7e23ea37b90a.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-84631H-MS-JUN22_PDF/ccd0922557bdcf0e5256cb825fa39064ae37ac3f.pdf'],  // Physics P1H
    '84631F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-84631F-QP-JUN22_PDF/f401a68d9b02fd50a5edb088c326069e35bbe195.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-84631F-MS-JUN22_PDF/d6ec46d0166c30069076d0a5e010989899cbd24f.pdf'],  // Physics P1F
    '84632H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-84632H-QP-JUN22_PDF/8ad5a83b6a44f298c4dcf4707cf68eb036c9e213.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-84632H-MS-JUN22_PDF/3fac4847ee3e0e6b4aaece44ee022991cfb302b9.pdf'],  // Physics P2H
    '84632F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-84632F-QP-JUN22_PDF/c8810396bb6b12958a82c125918973450bc323cd.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-84632F-MS-JUN22_PDF/f0b99263e7100deeb481a0e94752823ab4b7eed8.pdf'],  // Physics P2F
];

$JUN23_TRIPLE = [
    '84611H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-84611H-QP-JUN23_PDF/b9a9417f5d544beba8dae16fc3c10582ee5769e0.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-84611H-MS-JUN23_PDF/91864b27ed6dde51a4dd5d9bd3aa1977ce9c77aa.pdf'],  // Biology P1H
    '84611F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-84611F-QP-JUN23_PDF/822500b30d5cb586689ef6d3acb85deccb4a00da.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-84611F-MS-JUN23_PDF/2c75e27e9960556ada5402dc8588a939eb1db07c.pdf'],  // Biology P1F
    '84612H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-84612H-QP-JUN23_PDF/ee5dc019ad7306f397c539d30ceeaf1ca20f2298.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-84612H-MS-JUN23_PDF/844c077ac6f14b3242d5eb1e8beab67b565adf1a.pdf'],  // Biology P2H
    '84612F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-84612F-QP-JUN23_PDF/aa1d313cd32ea2e818f4680dd21969010f1e61f5.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-84612F-MS-JUN23_PDF/fd471d8de7d45f1c198aa2dcb8a76d7cb0529d81.pdf'],  // Biology P2F
    '84621H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-84621H-QP-JUN23_PDF/c586a6747c7a8b6c8f71bfc687df95d248b4a913.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-84621H-MS-JUN23_PDF/dd36b1e68755184bf9294f429bc1c78e72aa9836.pdf'],  // Chemistry P1H
    '84621F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-84621F-QP-JUN23_PDF/1060ddb8494c8e86f3b75000f892cbfd28544cc1.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-84621F-MS-JUN23_PDF/4711bb4794feba571d84c025d5d0c528cc9ad6f3.pdf'],  // Chemistry P1F
    '84622H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-84622H-QP-JUN23_PDF/799a81e2160f2f64faea8f4e276e0491077b9bf5.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-84622H-MS-JUN23_PDF/2639704c5e10b186b8f4f6b244dafe02c0d256a1.pdf'],  // Chemistry P2H
    '84622F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-84622F-QP-JUN23_PDF/a6c78e2ac0606d01e565a405ba23a35afde84810.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-84622F-MS-JUN23_PDF/e6a743d4ab3f79279828525d5ef953ea690771e8.pdf'],  // Chemistry P2F
    '84631H' => ['qp' => 'https://pmt.physicsandmathstutor.com/download/Physics/GCSE/Past-Papers/AQA/Paper-1H/QP/June%202023%20QP.pdf',  // PMT
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-84631H-MS-JUN23_PDF/3f135322537e2c1a6a08e1b575d97b02406bd1b9.pdf'],  // Physics P1H
    '84631F' => ['qp' => 'https://pmt.physicsandmathstutor.com/download/Physics/GCSE/Past-Papers/AQA/Paper-1F/QP/June%202023%20QP.pdf',  // PMT
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-84631F-MS-JUN23_PDF/475c8c2130e9efd93cca6ece96813d9f82d63320.pdf'],  // Physics P1F
    '84632H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-84632H-QP-JUN23_PDF/a3d5ef539ca38e622dffd5367814d4f27a01ca6f.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-84632H-MS-JUN23_PDF/b467a8fbdf62462b5b5d6fdd717261be60920967.pdf'],  // Physics P2H
    '84632F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-84632F-QP-JUN23_PDF/ac4dc33417cade268898614a7e3a3b66308242bb.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-84632F-MS-JUN23_PDF/4297d08dc09e1e6279d1c1f4839d00d0dfce5f9d.pdf'],  // Physics P2F
];

$JUN24_TRIPLE = [
    '84611H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-84611H-QP-JUN24-CR_PDF/45bce632101224bd077beea4b962c027353a7abc.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-84611H-MS-JUN24_PDF/8d711ebd0128dce40f21490bfc3ab7305e492fda.pdf'],  // Biology P1H
    '84611F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-84611F-QP-JUN24-CR_PDF/d6ecea931d1f56c5f0aa39c39f1b4064ffcd9f5f2.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-84611F-MS-JUN24_PDF/bb7a043f0d4b1f090ff15cc11875fa15798e4135.pdf'],  // Biology P1F
    '84612H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-84612H-QP-JUN24_PDF/dfafe238a1ff1ec7e60abaa5bed6107cecc4e382.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-84612H-MS-JUN24_PDF/923aa377ee5618115b99b54d437b51c0ac99453b.pdf'],  // Biology P2H
    '84612F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-84612F-QP-JUN24_PDF/af175ee50253d9e3f886b06fe23a9851be93a75a.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-84612F-MS-JUN24_PDF/20dcb789ef75500e442b8b30a1d84f350d90eee8.pdf'],  // Biology P2F
    '84621H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-84621H-QP-JUN24_PDF/eb7cb6b60d9dc2a5a58f7411da7a2d1dc5986518.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-84621H-MS-JUN24_PDF/dee152884c4ad5237f2ffc14c54e317ba3bb6ade.pdf'],  // Chemistry P1H
    '84621F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-84621F-QP-JUN24_PDF/19e5499fec0dfbbd7cf39329f8d717e63500bb8a.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-84621F-MS-JUN24_PDF/f796db4f901abd0481aecc4e1ed93f1192d33f92.pdf'],  // Chemistry P1F
    '84622H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-84622H-QP-JUN24_PDF/326d5b564a9b293b1219eb8296148ee29e0c88f6.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-84622H-MS-JUN24_PDF/12eb166b0abb29cbf238c467838ce510a66a36b3.pdf'],  // Chemistry P2H
    '84622F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-84622F-QP-JUN24_PDF/aee6458600a74c6f00375a7d2c744e534d3e5f7a.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-84622F-MS-JUN24_PDF/e4120d6dbdc247bea1ce0803dde052fcd043c5e5.pdf'],  // Chemistry P2F
    '84631H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-84631H-QP-JUN24_PDF/0b50579b41d427ef5709ce85971d363c4f3802d1.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-84631H-MS-JUN24_PDF/7188b187220753525bbb59e8104e2959f5069738.pdf'],  // Physics P1H
    '84631F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-84631F-QP-JUN24_PDF/abdfc90c249bc74d683e17dc34131d9912d0163d.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-84631F-MS-JUN24_PDF/23315cdcb36be5f622da27af72eacaff1a7f6050.pdf'],  // Physics P1F
    '84632H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-84632H-QP-JUN24_PDF/88dead88a2f67d2ef4e871edeec476e011774e40.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-84632H-MS-JUN24_PDF/2609e931025b6c3fbb82b127bc95de301de739b7.pdf'],  // Physics P2H
    '84632F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-84632F-QP-JUN24_PDF/210e2e219d964f449e4a39f3db0eaff6fc987bca.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-84632F-MS-JUN24_PDF/20b58ef0c72f9c1b2780d39fa833096a661fe0c0.pdf'],  // Physics P2F
];

$JUN25_TRIPLE = [
    '84611H' => ['qp' => 'https://www.aqa.org.uk/files/2vT3LmcUzNO62qpl9lrkLD/47dfa73fc713780cb812441c8b01c7fd9fb7206f.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/2vT3LmcUzNO62qpl9lrfxl/107c6e676d1afbe16dd83555c05fdd63013345a1.pdf'],  // Biology P1H
    '84611F' => ['qp' => 'https://www.aqa.org.uk/files/fHDU1dLPgDwvQhfFoL4kFM/a0f269916a712a08c79119756ed8982bc012d9b1.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/fHDU1dLPgDwvQhfFoL4ar6/11ccb01b84b16e76fbf49671377da2f59b15b11e.pdf'],  // Biology P1F
    '84612H' => ['qp' => 'https://www.aqa.org.uk/files/cRIpdimsSKiybuSFvPrpil/ec49086966c204b53a24ba8c62d5ae14a64849ac.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/2vT3LmcUzNO62qpl9lrtB3/dae808f93bdee75ea3e4d17f4a3f17a5a44f2562.pdf'],  // Biology P2H
    '84612F' => ['qp' => 'https://www.aqa.org.uk/files/2vT3LmcUzNO62qpl9lrq9V/41c34dc473b11ba6d0bd21ec5d885730c20fd4ce.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/2vT3LmcUzNO62qpl9lrlSJ/52aa82e4d6da45fae8b182384d8e5f11dfccb898.pdf'],  // Biology P2F
    '84621H' => ['qp' => 'https://www.aqa.org.uk/files/fHDU1dLPgDwvQhfFoL4xQ3/60cf3dec8a26a4d774305ddc558cec5fcf5bb264.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/fHDU1dLPgDwvQhfFoL4whc/59e853f8c451843ed97be757478941c5ac2f19e5.pdf'],  // Chemistry P1H
    '84621F' => ['qp' => 'https://www.aqa.org.uk/files/fHDU1dLPgDwvQhfFoL4tVx/bbf4010e2228c480edeb21fc13c957b5a35ea6dd.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/fHDU1dLPgDwvQhfFoL4rIA/26bae64da0a573e448e96e8fa290bb2dbee1dee3.pdf'],  // Chemistry P1F
    '84622H' => ['qp' => 'https://www.aqa.org.uk/files/cRIpdimsSKiybuSFvPsCky/ee1dfa784f0721caeee2b5f2e317ed68491a4baa.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/cRIpdimsSKiybuSFvPsC40/30eab43ad173a1857b2d9247fb338cfa21f5e8dd.pdf'],  // Chemistry P2H
    '84622F' => ['qp' => 'https://www.aqa.org.uk/files/fHDU1dLPgDwvQhfFoL4yQs/24f66d775f51a60954ca49eb85f7c281cd87d722.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/2vT3LmcUzNO62qpl9lsQID/07e45751ddbcca5d7629bc78f1dd5fbcdbe2f548.pdf'],  // Chemistry P2F
    '84631H' => ['qp' => 'https://www.aqa.org.uk/files/fHDU1dLPgDwvQhfFoL5UjX/c08bff2dd77ec7384f09d3a6db77a4bde9435176.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/2vT3LmcUzNO62qpl9ItP1z/964eb4c945ed6fdd78535806be65d1963b6396cf.pdf'],  // Physics P1H
    '84631F' => ['qp' => 'https://www.aqa.org.uk/files/cRIpdimsSKiybuSFvPsLIY/b71e7d990f82c61022434f38b0a663ba4d651af7.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/fHDU1dLPgDwvQhfFoL52R6/bcbcf930aed8d994ab5331c2819c84c4a9a224de.pdf'],  // Physics P1F
    '84632H' => ['qp' => 'https://www.aqa.org.uk/files/fHDU1dLPgDwvQhfFoL6ScC/c7fea85a8a1fbd9c801e6f075bf3919a721d4d82.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/2vT3LmcUzNO62qpl9lwZZh/3079bd0b0bf255ac16e714fa5ce29db1a8ed5ee8.pdf'],  // Physics P2H
    '84632F' => ['qp' => 'https://www.aqa.org.uk/files/cRIpdimsSKiybuSFvPuamA/ca4b44fab62012a77816f6ba1c0c06010bc9621d.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/2vT3LmcUzNO62qpl9lvY3B/5bb94eb3c10a0175e9748b4573e86c939a7badf4.pdf'],  // Physics P2F
];

$SPECIMEN_TRIPLE = [
    '84611H' => ['qp' => 'https://www.aqa.org.uk/files/resources.biology.AQA-84611H-SQP-CR_PDF/d22e3088d988dac25416eb85c59de05b6453065f.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/resources.biology.AQA-84611H-SMS_PDF/f7fd7d36179ffec140760465d10eaf368822c17f.pdf'],  // Biology P1H
    '84611F' => ['qp' => 'https://www.aqa.org.uk/files/resources.biology.AQA-84611F-SQP-CR_PDF/6d76b8a413c3d54e56a73e0553a4269c0884984a.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/resources.biology.AQA-84611F-SMS_PDF/11c384703144947ff45235fc23f3e510110a962b.pdf'],  // Biology P1F
    '84612H' => ['qp' => 'https://www.aqa.org.uk/files/resources.biology.AQA-84612H-SQP-CR_PDF/92ba9532bfc06c76d9941afadd73c37169eab7a7.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/resources.biology.AQA-84612H-SMS_PDF/229fbd8744fd07bd6ec1dc06a6de5a0357f8edaa.pdf'],  // Biology P2H
    '84612F' => ['qp' => 'https://www.aqa.org.uk/files/resources.biology.AQA-84612F-SQP-CR_PDF/f0d69e817e0a7f5aa79e91737d20ee6b8d0dd50f.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/resources.biology.AQA-84612F-SMS_PDF/52043b6a98d004d6bc11d7025c6604d356691d69.pdf'],  // Biology P2F
    '84621H' => ['qp' => 'https://www.aqa.org.uk/files/resources.chemistry.AQA-84621H-SQP_PDF/9c48dfca007bf534919f1b1c63a27621594771de.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/resources.chemistry.AQA-84621H-SMS_PDF/deeec39d77611e029aa13a7bd880b39cca5cd7c9.pdf'],  // Chemistry P1H
    '84621F' => ['qp' => 'https://www.aqa.org.uk/files/resources.chemistry.AQA-84621F-SQP_PDF/f18a4b440b7f82790cea5d9ae749b4383f12e4a0.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/resources.chemistry.AQA-84621F-SMS_PDF/25d21cb82a227ccf62209b7afd0c1d9511e573bd.pdf'],  // Chemistry P1F
    '84622H' => ['qp' => 'https://www.aqa.org.uk/files/resources.chemistry.AQA-84622H-SQP_PDF/9c0fd4b423df54429ac386c810be4454e5010307.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/resources.chemistry.AQA-84622H-SMS_PDF/8c3730e33ef5aae7585fd205a84440fced13bdd7.pdf'],  // Chemistry P2H
    '84622F' => ['qp' => 'https://www.aqa.org.uk/files/resources.chemistry.AQA-84622F-SQP_PDF/0fea29840739c1027be2c4504bc6692ab7c91061.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/resources.chemistry.AQA-84622F-SMS_PDF/e412e2897f3557a98bfff52987391fbab65a980a.pdf'],  // Chemistry P2F
    '84631H' => ['qp' => 'https://www.aqa.org.uk/files/resources.physics.AQA-84631H-SQP-CR_PDF/377c5c3536040f1291bed68143761e5d3fba8e0d.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/resources.physics.AQA-84631H-SMS_PDF/d9909eb3abb53ebed21eaf534f3f74195c4002a9.pdf'],  // Physics P1H
    '84631F' => ['qp' => 'https://www.aqa.org.uk/files/resources.physics.AQA-84631F-SQP-CR_PDF/6080675a29d3505fc787e6aa5764da1b2f06aeca.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/resources.physics.AQA-84631F-SMS_PDF/23a5a69729cdeda7d22e635d404f1d7fd416d1d4.pdf'],  // Physics P1F
    '84632H' => ['qp' => 'https://www.aqa.org.uk/files/resources.physics.AQA-84632H-SQP_PDF/849bb1783ef09fa4db1fd346819bdd9957127b3c.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/resources.physics.AQA-84632H-SMS_PDF/b0aff9b8aa97b2c74b1ddd1cecdeab08991975fe.pdf'],  // Physics P2H
    '84632F' => ['qp' => 'https://www.aqa.org.uk/files/resources.physics.AQA-84632F-SQP_PDF/e9014079b9f4bd4ca9f7c146907d73e1bcfc3716.pdf',
                 'ms' => 'https://www.aqa.org.uk/files/resources.physics.AQA-84632F-SMS_PDF/13e63faf5524e0ced459d981f2a121c443aecaa9.pdf'],  // Physics P2F
];

// ── Jun25 Combined Science — confirmed real URLs (new AQA hosting system,   ─
//    hand-verified from AQA's own resource listing, not a guessable pattern)
$JUN25_COMBINED = [
    '8464B1H' => ['qp' => 'https://www.aqa.org.uk/files/cRIpdimsSKiybuSFvPuvOS/f6ae968b094c13c675bd2e4ce6e82c920c0a23ce.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/2vT3LmcUzNO62qpl9IwmOJ/4847ca8961b6fa99dace2049737eaad5d76fff2a.pdf'],
    '8464B2H' => ['qp' => 'https://www.aqa.org.uk/files/cRIpdimsSKiybuSFvPv2fc/43e367a0282ef3fd7595ae7f6935a8f0b9a426dc.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/fHDU1dLPgDwvQhfFoL6d7P/22263e6bfd1f0c54fb7158c735f4caba44f58b2c.pdf'],
    '8464B1F' => ['qp' => 'https://www.aqa.org.uk/files/2vT3LmcUzNO62qpl9IwksX/66bbb178404c1bd5b677c6506d57e679b2821fab.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/fHDU1dLPgDwvQhfFoL6TeY/a8c7038293db49e87642a9e763638727fbae1286.pdf'],
    '8464B2F' => ['qp' => 'https://www.aqa.org.uk/files/2vT3LmcUzNO62qpl9Iwufb/9df4786f4814b7e0efa52bd6cb33d538f1a8243a.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/cRIpdimsSKiybuSFvPuwmO/6e5e83086f0986fce6eab687210cab2de14453ed.pdf'],
    '8464C1H' => ['qp' => 'https://www.aqa.org.uk/files/cRIpdimsSKiybuSFvPvGBU/443d1b55eb5d5071ef530a684092dd5ebb493c59.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/2vT3LmcUzNO62qpl9IxDcF/4f1159c009450178f2d4b11c66d93334a807345f.pdf'],
    '8464C2H' => ['qp' => 'https://www.aqa.org.uk/files/cRIpdimsSKiybuSFvPvj7O/9555e568f9626fb73491b7a1d9abf678d95f70bc.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/2vT3LmcUzNO62qpl9IxmTz/aab375dfa2e8e3223a154b55d53f28426412edd1.pdf'],
    '8464C1F' => ['qp' => 'https://www.aqa.org.uk/files/fHDU1dLPgDwvQhfFoL6hMx/bfc00b2a522b1c76d947777af9b9dc9dac88a482.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/cRIpdimsSKiybuSFvPv89m/52fe830fc85cae63bc7df51b4104275837abba61.pdf'],
    '8464C2F' => ['qp' => 'https://www.aqa.org.uk/files/2vT3LmcUzNO62qpl9IxLPv/9cc30d4bff240848dd1f864d89df03a9484dce95.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/cRIpdimsSKiybuSFvPvHuu/0830e28c5c467b0194cb0ea4a1f77b0def2331b9.pdf'],
    '8464P1H' => ['qp' => 'https://www.aqa.org.uk/files/cRIpdimsSKiybuSFvPxi8A/1ef695e518420a8a41ede4fbb5368380454c18ee.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/2vT3LmcUzNO62qpl9J0tqH/bc69c89df31a9a4805e1948984dde3080aa1168a.pdf'],
    '8464P2H' => ['qp' => 'https://www.aqa.org.uk/files/2vT3LmcUzNO62qpl9J1eUT/557ab3ffa78e39ea43e17a70cec009a36b1ddb9b.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/cRIpdimsSKiybuSFvPy274/499715589c92d30c59e08f3b860fb9780dbc2e2a.pdf'],
    '8464P1F' => ['qp' => 'https://www.aqa.org.uk/files/2vT3LmcUzNO62qpl9Izpvh/41e2d00e91fc885a46d0df9ae85309a740c061d3.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/cRIpdimsSKiybuSFvPwY3W/d5cf018591b8963b57e79a1065341485e5a898ba.pdf'],
    '8464P2F' => ['qp' => 'https://www.aqa.org.uk/files/cRIpdimsSKiybuSFvPxuUQ/c7cef3e5ac05f8b1180b1ec9e37972d13fe50429.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/2vT3LmcUzNO62qpl9J1O0p/9ca3dbcd8ae00a7fc1931e1b78ecb2c8d4894975.pdf'],
];

// ── Jun24 Combined Science — confirmed real URLs (old domain pattern,      ─
//    hand-verified; the filestore.aqa.org.uk guess was wrong for this year)
$JUN24_COMBINED = [
    '8464B1H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-8464B1H-QP-JUN24_PDF/6096e5f6035fb604a7ed66fdcccf6a7e3ceec505.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-8464B1H-MS-JUN24_PDF/8fc71395d1f56c970f944e7d19868f60431eb24b.pdf'],
    '8464B2H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-8464B2H-QP-JUN24_PDF/1265e09106910a480ff4c882b8a5888cbe2ac518.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-8464B2H-MS-JUN24_PDF/ccae730cf19f9be6d897b41b9fb085ee2545b333.pdf'],
    '8464B1F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-8464B1F-QP-JUN24_PDF/bdd5f99603141cf501f90cf14f923be8a301d1e9.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-8464B1F-MS-JUN24_PDF/4581d897772bc703cdb6d169c6f52f61236b5fbd.pdf'],
    '8464B2F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-8464B2F-QP-JUN24_PDF/749210294a38cc020fa4dfa71d7df85be78e8862.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-8464B2F-MS-JUN24_PDF/9cf234cae80983a6c91e34a820ec35c0bbbf1797.pdf'],
    '8464C1H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-8464C1H-QP-JUN24_PDF/34a8dd618ffda76af96f306062f05253719fc8a4.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-8464C1H-MS-JUN24_PDF/e3dcb4e72a6c8a2f6cfc37281cc757bf056f2b07.pdf'],
    '8464C2H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-8464C2H-QP-JUN24_PDF/caec3d1a8e1420448e19a1d0a1ab43106341a679.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-8464C2H-MS-JUN24_PDF/98daa775e1411bae7e7b14a572921443ae8a3505.pdf'],
    '8464C1F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-8464C1F-QP-JUN24_PDF/c9a764dd178e09f6859a99a1ea1d7e6219ca612a.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-8464C1F-MS-JUN24_PDF/8348ec288a8ec81a10938dcfdf1327571bd784e5.pdf'],
    '8464C2F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-8464C2F-QP-JUN24_PDF/a0efda365918ce3e6a0d697c2917ab65b50595e4.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-8464C2F-MS-JUN24_PDF/0dd15b58ae5dd72d719c4c458f6d8acd1f1fbf70.pdf'],
    '8464P1H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-8464P1H-QP-JUN24_PDF/6bc73d7ee5c5c33db434ba1bdd6bb006578ac679.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-8464P1H-MS-JUN24_PDF/ab48272f1bad887b0bcc74b44de2675731a7ca48.pdf'],
    '8464P2H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-8464P2H-QP-JUN24_PDF/b74391e3188d4abd577000fabd5d7b3f6d615f43.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-8464P2H-MS-JUN24_PDF/c6617de319539abb27a2e273c2c6620f1c249baa.pdf'],
    '8464P1F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-8464P1F-QP-JUN24_PDF/396c0d89bf5dd25e84ee3fca5581ed9584b8847f.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-8464P1F-MS-JUN24_PDF/6dd51395af97c58fbc196d347572788fe2c02362.pdf'],
    '8464P2F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-8464P2F-QP-JUN24_PDF/94034e5b781b0079638556533d92fa986d4264fe.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2024.June.AQA-8464P2F-MS-JUN24_PDF/7ee543e51bd4ad85af7b201bbfc31d68f92e9a00.pdf'],
];

// ── Jun23 Combined Science — confirmed, complete (12/12) ────────────────────
$JUN23_COMBINED = [
    '8464B1H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-8464B1H-QP-JUN23_PDF/0d3e0f85c203c68f4b056f828bab7010be91ec14.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-8464B1H-MS-JUN23_PDF/e31a1a687925134f27784ee606401ff4bf7dafd4.pdf'],
    '8464B2H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-8464B2H-QP-JUN23_PDF/0daee5053c8241ad0a166e4f413ccdc361086b12.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-8464B2H-MS-JUN23_PDF/e9ff0e5cd9ffcd15b41ab9772f431ff7ac99d464.pdf'],
    '8464B1F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-8464B1F-QP-JUN23_PDF/936323a2270f53f49ab862438d162b66c546bfa5.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-8464B1F-MS-JUN23_PDF/442d64cb2019ba3c7d27b78aefc9685bbc1597b3.pdf'],
    '8464B2F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-8464B2F-QP-JUN23_PDF/309999e70d3fe015a8066a2ecf0636c22ce17b63.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-8464B2F-MS-JUN23_PDF/42691c042b3021a065592666bc40c6ad6ba0e179.pdf'],
    '8464C1H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-8464C1H-QP-JUN23_PDF/e2f07125677d4db67412f2f903d6ab49bbdf2fa2.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-8464C1H-MS-JUN23_PDF/bc0c8c844f5dad348c03f7493f09d77e22285931.pdf'],
    '8464C2H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-8464C2H-QP-JUN23_PDF/f7901e77f28a349542512650d40aedf2462fd8aa.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-8464C2H-MS-JUN23_PDF/a88372d6c80ecb27fede06edd19cb90220d07b11.pdf'],
    '8464C1F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-8464C1F-QP-JUN23_PDF/afe4c169d2ce96e8b9322b0a58f93fb4cd3fb076.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-8464C1F-MS-JUN23_PDF/ba4c248b3c51639967664e956d36e2ebdaefde89.pdf'],
    '8464C2F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-8464C2F-QP-JUN23_PDF/50e147ef6079fe030b5f3ccb6230eb17a3430c9d.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-8464C2F-MS-JUN23_PDF/4ba36d1296b8b00debdbca594f8e502c14b703ea.pdf'],
    '8464P1H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-8464P1H-QP-JUN23_PDF/5a84b4dfab2bf60404621638058e3b8cfdc811ed.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-8464P1H-MS-JUN23_PDF/e72174fd3f7d9fae984ea429ac7f43f2f796833b.pdf'],
    '8464P2H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-8464P2H-QP-JUN23_PDF/e7eafc896cb4b1a383af7eac591fcb7c753f3cd2.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-8464P2H-MS-JUN23_PDF/5e2fe0ed4db0ab732d0a74ccde6bba73b4a3850b.pdf'],
    '8464P1F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-8464P1F-QP-JUN23_PDF/15e37eb2c19dd8e01efb652824d804c3ac2027e2.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-8464P1F-MS-JUN23_PDF/5495eb919017d83c2dd5b2252e7de769a1a6145b.pdf'],
    '8464P2F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-8464P2F-QP-JUN23_PDF/d3da11954d27125c53abe07a4d912f8f299a22f0.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2023.june.AQA-8464P2F-MS-JUN23_PDF/410a68110b8f76bb93edba3e40bd586fba95d85f.pdf'],
];

// ── Jun22 Combined Science — confirmed, complete (12/12). Note: C2F QP has ─
//    a "-CR" suffix (Clean Reissue) — verified real and correct.
$JUN22_COMBINED = [
    '8464B1H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-8464B1H-QP-JUN22_PDF/799b6708084fc1dbc7cd44fbe714f0eb4334478a.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-8464B1H-MS-JUN22_PDF/0bce219c2e860bd8488e37880703862ec7edd29c.pdf'],
    '8464B2H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-8464B2H-QP-JUN22_PDF/bae7e15087eacaa9765c45ff5c4787cef97aab5b.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-8464B2H-MS-JUN22_PDF/29ed2b7cb94e86020470df1c7ecff98c7154a68d.pdf'],
    '8464B1F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-8464B1F-QP-JUN22_PDF/d606d03d6f80f3efd0804ed73755a40b47ea8c44.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-8464B1F-MS-JUN22_PDF/10d35a565fbb6f40581134253b8cf35bc265a183.pdf'],
    '8464B2F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-8464B2F-QP-JUN22_PDF/82d75ebade1390880c7b8a478ea8d6067307dbbb.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-8464B2F-MS-JUN22_PDF/6f938299db677f027c534f9584a0d8ad120dc3fd.pdf'],
    '8464C1H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-8464C1H-QP-JUN22_PDF/64e470935a0d6b05f716ab5c877221fb7955ef0c.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-8464C1H-MS-JUN22_PDF/b5f494603448e20f769bfc63b04466104b0f08fb.pdf'],
    '8464C2H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-8464C2H-QP-JUN22_PDF/5f8bd169dfc64f9e5feac4cd776153ff18d6b17f.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-8464C2H-MS-JUN22_PDF/0dbf64943734d0afffe5110a7232db301a58420f.pdf'],
    '8464C1F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-8464C1F-QP-JUN22_PDF/c846a2b1bc2cc90d820a67f46fc9093a588607b1.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-8464C1F-MS-JUN22_PDF/0e2df582db91517509438dd1a9c7a3ec23a881cf.pdf'],
    '8464C2F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-8464C2F-QP-JUN22-CR_PDF/e089a833684bf969dab49a1decb7ab9e4e38c0b9.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-8464C2F-MS-JUN22_PDF/44ae6709f293a7a2c909d8378f91a6a50a318a16.pdf'],
    '8464P1H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-8464P1H-QP-JUN22_PDF/53ad6a26485e7c9c5caf5c3411d3ed99e0a11ea3.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-8464P1H-MS-JUN22_PDF/aaba11a510ac813b2e97e0c016a870f54d7c7cb9.pdf'],
    '8464P2H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-8464P2H-QP-JUN22_PDF/c3f850f424afe9593401338e482f6faf32e7ab69.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-8464P2H-MS-JUN22_PDF/8c9f721f1cfdd2aad12bab0ce48381941f1813fa.pdf'],
    '8464P1F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-8464P1F-QP-JUN22_PDF/e525de23755e6bd3752036bed9c81942f2e7984d.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-8464P1F-MS-JUN22_PDF/34ad9a3f2178fb65bcdb8a52d3dfc74cd19d4632.pdf'],
    '8464P2F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-8464P2F-QP-JUN22_PDF/13e72f72496fdfdd9a5d004ddd13685edff4d464.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2022.june.AQA-8464P2F-MS-JUN22_PDF/b6b7094159b07d4bd86f90f59c421c35c2a9ab7f.pdf'],
];

// ── Nov21 Combined Science — confirmed, complete (12/12) ────────────────────
$NOV21_COMBINED = [
    '8464B1H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-8464B1H-QP-NOV21_PDF/96954d60b16f92618f61930f23e573bd5bc34850.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-8464B1H-MS-NOV21_PDF/327beba8f011469d605c1ae3fb684eaea84979c2.pdf'],
    '8464B2H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-8464B2H-QP-NOV21_PDF/757a37d993cd88adf3a78312fd0b53357e898aca.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-8464B2H-MS-NOV21_PDF/858dd66bf501361632f2a0d1ddd55983705ecac4.pdf'],
    '8464B1F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-8464B1F-QP-NOV21_PDF/9b2d02c32b2d5b07e3913b3c981181bf0b47c8d6.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-8464B1F-MS-NOV21_PDF/618825357451efb937be5ef58faf19d7e373d3e0.pdf'],
    '8464B2F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-8464B2F-QP-NOV21_PDF/c9b0a66cec824b70a4b3dfa37d8d35b835ef9636.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-8464B2F-MS-NOV21_PDF/826c41e7c3442ab2f505022284daea854df2a7ae.pdf'],
    '8464C1H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-8464C1H-QP-NOV21_PDF/4a712a2bba26541a7f07dd9fc1285980a38f4b1e.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-8464C1H-MS-NOV21_PDF/bcd60099c73a2a5c4f29229f68961e9a8f78df09.pdf'],
    '8464C2H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-8464C2H-QP-NOV21_PDF/d8199f4626b838aa349c6fa3e5c4bfa90594123f.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-8464C2H-MS-NOV21_PDF/f9bd186a74db444c4a5dde4f22fd2d47bf9f3199.pdf'],
    '8464C1F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-8464C1F-QP-NOV21_PDF/412d899006b62c9668ec41818f0ccbf892ed7d0f.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-8464C1F-MS-NOV21_PDF/aba87d20de08cac78a814aaf769678aa33d2ddb2.pdf'],
    '8464C2F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-8464C2F-QP-NOV21_PDF/a4f88a2c2b422f8353263bcfd918d77c92421c9e.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-8464C2F-MS-NOV21_PDF/745ee9fbd1ada20c458ebb058b2e171dccae1b58.pdf'],
    '8464P1H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-8464P1H-QP-NOV21_PDF/ba272ec600b88dfcfc4b23aa4b2f6f42cf24c3cf.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-8464P1H-MS-NOV21_PDF/91a8b3a41b88897573e9b507c676fb0c5d1a237f.pdf'],
    '8464P2H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-8464P2H-QP-NOV21_PDF/23ff0482c6e2bc63f03ebe84cd1f75c5d646a7c6.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-8464P2H-MS-NOV21_PDF/b1664e705b363e7e38ffb8cb52b09bc9148aac32.pdf'],
    '8464P1F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-8464P1F-QP-NOV21_PDF/7433c9314dae899d6df762de6f73eb0dd07d181d.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-8464P1F-MS-NOV21_PDF/ef36a2ed88352f5cb60811da8b73ee7b834e0239.pdf'],
    '8464P2F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-8464P2F-QP-NOV21_PDF/7d168da103027ecb51df4abcde8964c633acc67b.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2021.november.AQA-8464P2F-MS-NOV21_PDF/20075ba9748cbbd5bdc0ebf44b86cb5b42b5b583.pdf'],
];

// ── Nov20 Combined Science — confirmed, complete (12/12) ────────────────────
$NOV20_COMBINED = [
    '8464B1H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-8464B1H-QP-NOV20_PDF/9e97767125f79cc3f5f21511fe9e8dfbbced66fc.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-8464B1H-W-MS-NOV20_PDF/f7e1c7e042ed5c909a2e86150cc83706539e5501.pdf'],
    '8464B2H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-8464B2H-QP-NOV20_PDF/145d95e26af7a3aaf922651054006b09d40528a2.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-8464B2H-W-MS-NOV20_PDF/297154f1bec71831c2f78b0c3e0e3c9040c41b7a.pdf'],
    '8464B1F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-8464B1F-QP-NOV20_PDF/f41de1e6e6509785b492fa0b47b91bc13485806f.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-8464B1F-W-MS-NOV20_PDF/b4760abe397b45bf93b09ea0bce1033cd63dc8cd.pdf'],
    '8464B2F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-8464B2F-QP-NOV20_PDF/7513eb6377e4281e1e0ec4856a52286675541047.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-8464B2F-W-MS-NOV20_PDF/3a9a3c68348aca1d03c24a6a376fefa415b81fe6.pdf'],
    '8464C1H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-8464C1H-QP-NOV20_PDF/c9ff1feffc6002f38bee165fb8dfa3ed1368a41b.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-8464C1H-W-MS-NOV20_PDF/eafb61982f3fd5703b37564f8b411b55902002df.pdf'],
    '8464C2H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-8464C2H-QP-NOV20_PDF/8e92aba23a47db644043d90d9868373e9195679d.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-8464C2H-W-MS-NOV20_PDF/54b6befbd25c74daf33c740e261dd68c913e98ce.pdf'],
    '8464C1F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-8464C1F-QP-NOV20_PDF/d04d15f8698babd21b5ff1d8060ec45f94d5143e.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-8464C1F-W-MS-NOV20_PDF/9e987f5326c26cc7a492c5ccb60d634574cbaddc.pdf'],
    '8464C2F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-8464C2F-QP-NOV20_PDF/8d215ae7ef0e6383fcc27bf829231bc41627e33f.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-8464C2F-W-MS-NOV20_PDF/ccd222c936a76b46787ef63f59621d2bd4b4073d.pdf'],
    '8464P1H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-8464P1H-QP-NOV20_PDF/c55c64ce6fbc907dd4e1386642347cf157f700a1.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-8464P1H-W-MS-NOV20_PDF/77abd3d9eb118dedf76405bcbff33c88329c4e01.pdf'],
    '8464P2H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-8464P2H-QP-NOV20_PDF/8a95099d0ed4575ab1353cab146890d192d94ded.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-8464P2H-W-MS-NOV20_PDF/6e2bb88d9fab95d5a43ded30c9caf4a7670a7882.pdf'],
    '8464P1F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-8464P1F-QP-NOV20_PDF/2282a969b76d3c5797cbff5158399016df070696.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-8464P1F-W-MS-NOV20_PDF/1140303f78f9b9cc62cddcb73c6eacb41ee0a470.pdf'],
    '8464P2F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-8464P2F-QP-NOV20_PDF/355bae0c0f8d5c31eca81d05e3115377e98a4fed.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2020.november.AQA-8464P2F-W-MS-NOV20_PDF/a69b134671e7bc3b801417acd83c0ff2f26842c5.pdf'],
];

// ── Jun19 Combined Science — PARTIAL (7/12). Missing: B1F, B2F, C2F, P2F,  ─
//    P2H — confirmed genuinely hard to find on AQA's own search (see email
//    sent to gcsescience@aqa.org.uk). Codes simply absent = graceful 404.
$JUN19_COMBINED = [
    '8464C1H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-8464C1H-QP-JUN19_PDF/8a915ecb7f66573bdd740c507c35fefa8e1b69d9.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-8464C1H-W-MS-JUN19_PDF/ccd8c27cb916a1f518a64baa4d12ddc889df5c33.pdf'],
    '8464B1H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-8464B1H-QP-JUN19_PDF/7d8ea9573982436126b44df0b34760c91ef7cb5d.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-8464B1H-W-MS-JUN19_PDF/7be6bc2b67b8e52e04897dfeebf13d2082840d9d.pdf'],
    '8464P1F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-8464P1F-QP-JUN19_PDF/2ad3c9b46513caa70e678d411ee5bc6868ff3979.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-8464P1F-W-MS-JUN19_PDF/1f695fa647609d8e1e3e2480907c4e1d9814949f.pdf'],
    '8464P1H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-8464P1H-QP-JUN19_PDF/4cbd6fc355f0c2a0f089f00af56a64d945e62645.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-8464P1H-W-MS-JUN19_PDF/8a4cb1808a635f5c056bb17126c2526a47378fde.pdf'],
    '8464C1F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-8464C1F-QP-JUN19_PDF/5e274eb423681359abc606df3c4f05e3b302dad7.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-8464C1F-W-MS-JUN19_PDF/7f22f5afb2ad19c159c50162ac3abb79232615ca.pdf'],
    '8464B2H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-8464B2H-QP-JUN19_PDF/2cac0da2142fe5a73267f00c98064f6a8d0f6d47.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-8464B2H-W-MS-JUN19_PDF/53d2a9a6d55ec3f215e0f53245b5e5a96dc0ca88.pdf'],
    '8464C2H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-8464C2H-QP-JUN19_PDF/644ba32bba82ff1d62d7550ec11700aee49eada4.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-8464C2H-W-MS-JUN19_PDF/9a5d70c9119c00b7d3e4322d2a59105f8e955021.pdf'],
    '8464P2F' => ['qp' => 'https://pmt.physicsandmathstutor.com/download/Science/GCSE/Past-Papers/AQA/Physics-2F/QP/June%202019%20QP.pdf', // PMT
                  'ms' => 'https://pmt.physicsandmathstutor.com/download/Science/GCSE/Past-Papers/AQA/Physics-2F/MS/June%202019%20MS.pdf'], // PMT
    '8464C2F' => ['qp' => 'https://pmt.physicsandmathstutor.com/download/Science/GCSE/Past-Papers/AQA/Chemistry-2F/QP/June%202019%20QP.pdf', // PMT
                  'ms' => 'https://pmt.physicsandmathstutor.com/download/Science/GCSE/Past-Papers/AQA/Chemistry-2F/MS/June%202019%20MS.pdf'], // PMT
    '8464B1F' => ['qp' => 'https://pmt.physicsandmathstutor.com/download/Science/GCSE/Past-Papers/AQA/Biology-1F/QP/June%202019%20QP.pdf', // PMT
                  'ms' => 'https://pmt.physicsandmathstutor.com/download/Science/GCSE/Past-Papers/AQA/Biology-1F/MS/June%202019%20MS.pdf'], // PMT
    '8464B2F' => ['qp' => 'https://pmt.physicsandmathstutor.com/download/Science/GCSE/Past-Papers/AQA/Biology-2F/QP/June%202019%20QP.pdf', // PMT
                  'ms' => 'https://pmt.physicsandmathstutor.com/download/Science/GCSE/Past-Papers/AQA/Biology-2F/MS/June%202019%20MS.pdf'], // PMT
    '8464P2H' => ['qp' => 'https://pmt.physicsandmathstutor.com/download/Science/GCSE/Past-Papers/AQA/Physics-2H/QP/June%202019%20QP.pdf', // PMT
                  'ms' => 'https://pmt.physicsandmathstutor.com/download/Science/GCSE/Past-Papers/AQA/Physics-2H/MS/June%202019%20MS.pdf'], // PMT
];
// Combined Science table complete — 12/12 for every year, all three subjects,
// both tiers, Specimen through Jun25.

// ── Jun18 Combined Science — complete (12/12). B1F, B2F, C2F, P1F, P2F    ─
//    sourced from PMT (physicsandmathstutor.com) — genuine AQA-side gap,   ─
//    confirmed via search, same pattern as the Jun19 gaps below.
$JUN18_COMBINED = [
    '8464C1F' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-8464C1F-QP-JUN18_PDF/4cd959a9001fdc1920b5539abe77c019f4f8c5d2.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-8464C1F-W-MS-JUN18_PDF/3e162af89b21f261094f0eda4d8001ab57b64a0f.pdf'],
    '8464B1H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-8464B1H-QP-JUN18_PDF/32d2a1938e9e200bb1164f6354f456513341c477.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-8464B1H-W-MS-JUN18_PDF/721df445a5243120449fb3dd14fd31fa2554e972.pdf'],
    '8464C1H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-8464C1H-QP-JUN18_PDF/79b12f00bdd2d3937bae8a0ddc6da8763fba7300.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-8464C1H-W-MS-JUN18_PDF/a3d6a8b6dedd66cbe36d9cbe808dd49c499c3419.pdf'],
    '8464P1H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-8464P1H-QP-JUN18_PDF/8ccf9745b3c44efeb5d979aa69031e87bb979073.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-8464P1H-W-MS-JUN18_PDF/3df3202af9f445646968d46e283527fd8aba903a.pdf'],
    '8464B2H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-8464B2H-QP-JUN18_PDF/95b0b41e5dab2c1c6c4f648a724158529edc322f.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-8464B2H-W-MS-JUN18_PDF/3036fbc8ffc9537def10daa102ecbbc280b34adc.pdf'],
    '8464C2H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-8464C2H-QP-JUN18_PDF/ea6324ca45ad9231b64eacfe965aa152dd75b2cd.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-8464C2H-W-MS-JUN18_PDF/17b010417f39d745c0b9616aa6ea43b7376ff576.pdf'],
    '8464P2H' => ['qp' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-8464P2H-QP-JUN18_PDF/3d29cf05ec3a905ad443a77014a6ce234c40183d.pdf',
                  'ms' => 'https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2018.june.AQA-8464P2H-W-MS-JUN18_PDF/4e8bc7e0604ae78cbf2c329008d9e7c44508516b.pdf'],
    '8464P1F' => ['qp' => 'https://pmt.physicsandmathstutor.com/download/Science/GCSE/Past-Papers/AQA/Physics-1F/QP/June%202018%20QP.pdf', // PMT
                  'ms' => 'https://pmt.physicsandmathstutor.com/download/Science/GCSE/Past-Papers/AQA/Physics-1F/MS/June%202018%20MS.pdf'], // PMT
    '8464P2F' => ['qp' => 'https://pmt.physicsandmathstutor.com/download/Science/GCSE/Past-Papers/AQA/Physics-2F/QP/June%202018%20QP.pdf', // PMT
                  'ms' => 'https://pmt.physicsandmathstutor.com/download/Science/GCSE/Past-Papers/AQA/Physics-2F/MS/June%202018%20MS.pdf'], // PMT
    '8464C2F' => ['qp' => 'https://pmt.physicsandmathstutor.com/download/Science/GCSE/Past-Papers/AQA/Chemistry-2F/QP/June%202018%20QP.pdf', // PMT
                  'ms' => 'https://pmt.physicsandmathstutor.com/download/Science/GCSE/Past-Papers/AQA/Chemistry-2F/MS/June%202018%20MS.pdf'], // PMT
    '8464B1F' => ['qp' => 'https://pmt.physicsandmathstutor.com/download/Science/GCSE/Past-Papers/AQA/Biology-1F/QP/June%202018%20QP.pdf', // PMT
                  'ms' => 'https://pmt.physicsandmathstutor.com/download/Science/GCSE/Past-Papers/AQA/Biology-1F/MS/June%202018%20MS.pdf'], // PMT
    '8464B2F' => ['qp' => 'https://pmt.physicsandmathstutor.com/download/Science/GCSE/Past-Papers/AQA/Biology-2F/QP/June%202018%20QP.pdf', // PMT
                  'ms' => 'https://pmt.physicsandmathstutor.com/download/Science/GCSE/Past-Papers/AQA/Biology-2F/MS/June%202018%20MS.pdf'], // PMT
];

$code = $_GET['code'] ?? '';
$type = $_GET['type'] ?? '';
$year = $_GET['year'] ?? '';

// ── Validate strictly — only real GCSE AQA Science paper codes allowed ───────
// Combined: 8464 + B/C/P + 1/2 + H/F   e.g. 8464B1H
// Triple:   8461/8462/8463 + 1/2 + H/F e.g. 84611H
$validCode = preg_match('/^(8464[BCP][12][HF]|846[123][12][HF])$/', $code);
$validType = in_array($type, ['qp', 'ms'], true);

if (!$validCode || !$validType) {
    http_response_code(400);
    echo 'Invalid parameters';
    exit;
}

// ── Year dispatch — every year now has BOTH a Combined table and a Triple  ─
//    table (added Aug 2026, hand-verified paper-by-paper same as Combined).
//    Each code is looked up in whichever table actually has it; Combined and
//    Triple codes never collide (8464... vs 846[123]...) so checking both in
//    sequence is safe. Third-party (PMT/MME) URLs live inside the Triple
//    tables themselves, each one commented at its own array entry above.
$YEAR_TABLES = [
    'Specimen' => ['combined' => $SPECIMEN_COMBINED ?? [], 'triple' => $SPECIMEN_TRIPLE ?? []],
    'Jun25'    => ['combined' => $JUN25_COMBINED ?? [],    'triple' => $JUN25_TRIPLE ?? []],
    'Jun24'    => ['combined' => $JUN24_COMBINED ?? [],    'triple' => $JUN24_TRIPLE ?? []],
    'Jun23'    => ['combined' => $JUN23_COMBINED ?? [],    'triple' => $JUN23_TRIPLE ?? []],
    'Jun22'    => ['combined' => $JUN22_COMBINED ?? [],    'triple' => $JUN22_TRIPLE ?? []],
    'Nov21'    => ['combined' => $NOV21_COMBINED ?? [],    'triple' => $NOV21_TRIPLE ?? []],
    'Nov20'    => ['combined' => $NOV20_COMBINED ?? [],    'triple' => $NOV20_TRIPLE ?? []],
    'Jun19'    => ['combined' => $JUN19_COMBINED ?? [],    'triple' => $JUN19_TRIPLE ?? []],
    'Jun18'    => ['combined' => $JUN18_COMBINED ?? [],    'triple' => $JUN18_TRIPLE ?? []],
];

if (isset($YEAR_TABLES[$year])) {
    $tables = $YEAR_TABLES[$year];
    $url = $tables['combined'][$code][$type] ?? $tables['triple'][$code][$type] ?? null;
    if ($url === null) {
        http_response_code(404);
        $known_gap = in_array($year, ['Jun18', 'Jun19'], true);
        echo $known_gap
            ? "This {$year} paper is a known AQA-side gap (not on AQA's own search as of this build; sourced from public third-party mirrors where confirmed)"
            : "No {$year} paper available for this code";
        exit;
    }
} elseif (isset($YEARS[$year])) {
    $y = $YEARS[$year];
    $seg = ($type === 'ms' && $y['old']) ? 'W-MS' : strtoupper($type);
    $url = "https://filestore.aqa.org.uk/sample-papers-and-mark-schemes/{$y['year']}/{$y['month']}/AQA-{$code}-{$seg}-{$y['suffix']}.PDF";
} else {
    http_response_code(400);
    echo 'Invalid year';
    exit;
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (SPOTTER folder-download-test)',
]);
$data = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($data === false || $httpCode !== 200 || strlen($data) < 1000) {
    // Common and expected: not every paper/year combo exists (e.g. some
    // subjects didn't publish a Jun24 series, or Nov series is Higher-only
    // for some components). The frontend treats this as a normal "skip".
    http_response_code(404);
    echo "Not found (HTTP $httpCode) — this paper/year combination may not exist: $url";
    exit;
}

header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($data));
echo $data;
