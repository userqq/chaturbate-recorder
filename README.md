# chaturbate-recorder

## 
.env file sample
``` 
ACCESS_TOKEN="<you access token for vk.com api>"
TEMPORARY_PATH="/tmp/"
FFMPEG=false 
```

You can use [https://ffmpeg.org/](url) library instead of php file handler to reencode video to proper mp4 or any other supported format by installing ffmpeg lib and setting `FFMPEG` option in `.env` file to true
