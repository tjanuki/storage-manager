# S3 Configuration for Video Upload

## Required Environment Variables

Add these to your `.env` file:

```env
AWS_ACCESS_KEY_ID=your-access-key-id
AWS_SECRET_ACCESS_KEY=your-secret-access-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket-name
```

## AWS S3 Bucket Setup

1. **Create an S3 Bucket:**
   - Go to AWS S3 Console
   - Create a new bucket with a unique name
   - Choose your preferred region (e.g., us-east-1)

2. **Configure Bucket CORS:**
   The bucket needs CORS configuration to allow browser uploads. Add this CORS policy to your bucket:

   ```json
   [
       {
           "AllowedHeaders": ["*"],
           "AllowedMethods": ["GET", "PUT", "POST", "DELETE", "HEAD"],
           "AllowedOrigins": ["http://localhost:*", "https://yourdomain.com"],
           "ExposeHeaders": ["ETag"],
           "MaxAgeSeconds": 3000
       }
   ]
   ```

3. **IAM User Permissions:**
   Create an IAM user with the following policy:

   ```json
   {
       "Version": "2012-10-17",
       "Statement": [
           {
               "Effect": "Allow",
               "Action": [
                   "s3:PutObject",
                   "s3:PutObjectAcl",
                   "s3:GetObject",
                   "s3:GetObjectAcl",
                   "s3:DeleteObject",
                   "s3:ListBucket",
                   "s3:ListBucketMultipartUploads",
                   "s3:ListMultipartUploadParts",
                   "s3:AbortMultipartUpload"
               ],
               "Resource": [
                   "arn:aws:s3:::your-bucket-name/*",
                   "arn:aws:s3:::your-bucket-name"
               ]
           }
       ]
   }
   ```

4. **Generate Access Keys:**
   - Go to IAM → Users → Your User → Security credentials
   - Create access key
   - Copy the Access Key ID and Secret Access Key to your `.env` file

## Database Migration

Run the migration to create the videos table:

```bash
php artisan migrate
```

## Testing the Upload

1. Start your development server:
   ```bash
   composer dev
   ```

2. Navigate to `/videos` in your browser
3. Click "Upload Video" to test the upload functionality

## Features

- **Large File Support:** Handles video uploads up to 10GB
- **Multipart Upload:** Files are chunked (100MB chunks) for reliable uploads
- **Resume Support:** Can handle network interruptions
- **Progress Tracking:** Real-time upload progress display
- **Secure URLs:** Pre-signed URLs for secure access to videos

## Troubleshooting

- **CORS Errors:** Ensure the CORS policy is correctly applied to your S3 bucket
- **Access Denied:** Check IAM permissions and ensure credentials are correct
- **Upload Failures:** Check Laravel logs and browser console for detailed error messages
- **Large Files:** Ensure PHP `post_max_size` and `upload_max_filesize` are not limiting factors (though multipart upload bypasses these limits)