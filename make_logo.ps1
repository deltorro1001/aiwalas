$ErrorActionPreference='Stop'
Add-Type -AssemblyName System.Drawing
$out='D:\aiwalas\kader-penataan-lingkungan.png'
$bmp=New-Object System.Drawing.Bitmap 1024,1024
$g=[System.Drawing.Graphics]::FromImage($bmp)
$g.SmoothingMode=[System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
$g.Clear([System.Drawing.Color]::FromArgb(244,251,243))
$green=[System.Drawing.Color]::FromArgb(31,107,69); $leaf=[System.Drawing.Color]::FromArgb(78,158,91); $light=[System.Drawing.Color]::FromArgb(221,242,216); $gold=[System.Drawing.Color]::FromArgb(240,184,77); $blue=[System.Drawing.Color]::FromArgb(191,232,245)
$g.FillEllipse((New-Object Drawing.SolidBrush $light),222,130,580,580)
$g.FillPie((New-Object Drawing.SolidBrush $leaf),140,615,744,190,0,180)
$pen=New-Object Drawing.Pen $green,24
$house=New-Object Drawing.Point[] 5
$house[0]=New-Object Drawing.Point(338,390);$house[1]=New-Object Drawing.Point(512,250);$house[2]=New-Object Drawing.Point(686,390);$house[3]=New-Object Drawing.Point(686,575);$house[4]=New-Object Drawing.Point(338,575)
$g.FillPolygon((New-Object Drawing.SolidBrush ([Drawing.Color]::White)),$house);$g.DrawPolygon($pen,$house)
$roof=New-Object Drawing.Point[] 3;$roof[0]=New-Object Drawing.Point(302,405);$roof[1]=New-Object Drawing.Point(512,230);$roof[2]=New-Object Drawing.Point(722,405);$g.DrawLines((New-Object Drawing.Pen $green,30),$roof)
$g.FillRectangle((New-Object Drawing.SolidBrush $gold),466,470,92,105);$g.DrawRectangle($pen,466,470,92,105)
$winpen=New-Object Drawing.Pen $green,14
$g.FillRectangle((New-Object Drawing.SolidBrush $blue),380,430,75,70);$g.DrawRectangle($winpen,380,430,75,70)
$g.FillRectangle((New-Object Drawing.SolidBrush $blue),569,430,75,70);$g.DrawRectangle($winpen,569,430,75,70)
$leafPen=New-Object Drawing.Pen $green,18
$g.FillEllipse((New-Object Drawing.SolidBrush $leaf),215,405,120,220);$g.DrawEllipse($leafPen,215,405,120,220)
$g.FillEllipse((New-Object Drawing.SolidBrush $leaf),689,405,120,220);$g.DrawEllipse($leafPen,689,405,120,220)
$font1=New-Object Drawing.Font('Arial',48,[Drawing.FontStyle]::Bold);$font2=New-Object Drawing.Font('Arial',42,[Drawing.FontStyle]::Bold);$brush=New-Object Drawing.SolidBrush $green;$fmt=New-Object Drawing.StringFormat;$fmt.Alignment=[Drawing.StringAlignment]::Center
$g.DrawString('KADER PENATAAN',$font1,$brush,512,850,$fmt);$g.DrawString('LINGKUNGAN',$font2,(New-Object Drawing.SolidBrush $leaf),512,908,$fmt)
$g.Dispose();$bmp.Save($out,[Drawing.Imaging.ImageFormat]::Png);$bmp.Dispose();Write-Output $out
