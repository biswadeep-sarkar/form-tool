<?php 

return [
    // Do not put any slash in the end
    'adminURL' => '/admin',

    // Set route to redirect after login
    'loginRedirect' => '/dashboard',

    // Set root upload directory inside public folder
    // Let's assume we named it 'uploads'
    'uploadPath' => 'uploads',

    // Upload files in date directories. Like month-year directory
    // It will create one more sub directory under the route directory or uploadPath
    // Then our full upload path will be uploads/07-2022/
    // Leave blank if you don't want to use
    // Possible values: date time format or blank
    'uploadSubDirFormat' => 'm-Y',

    // Allowed types for file upload
    'allowedTypes' => 'jpg,jpeg,png,webp,gif,svg,bmp,tif,pdf,docx,doc,xls,xlsx,rtf,txt,ppt,csv,pptx,webm,mkv,flv,vob,avi,mov,mp3,mp4,m4p,mpg,mpeg,mp2,svi,3gp,rar,zip,psd,dwg,eps,xlr,db,dbf,mdb,html,tar.gz,zipx',

    // Allowed types for image upload
    'imageTypes' => 'jpg,jpeg,png,webp,gif,svg,bmp,tif'
];