<?php

namespace App\Console\Commands;

use Aws\S3\S3Client;
use Illuminate\Console\Command;

class ConfigureS3Cors extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 's3:configure-cors';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configure CORS settings for the S3 bucket';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $bucket = config('filesystems.disks.s3.bucket');
        $region = config('filesystems.disks.s3.region', 'us-east-1');
        
        $this->info("Configuring CORS for bucket: {$bucket} in region: {$region}");
        
        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => $region,
            'credentials' => [
                'key' => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
            ],
        ]);
        
        $corsConfig = [
            'CORSRules' => [
                [
                    'AllowedHeaders' => ['*'],
                    'AllowedMethods' => ['GET', 'PUT', 'POST', 'DELETE', 'HEAD'],
                    'AllowedOrigins' => [
                        'http://localhost:8000',
                        'http://localhost:5173',
                        'http://storage-manager.test',
                        'https://storage-manager.test',
                        config('app.url'),
                    ],
                    'ExposeHeaders' => ['ETag', 'x-amz-request-id', 'x-amz-id-2'],
                    'MaxAgeSeconds' => 3000,
                ],
            ],
        ];
        
        try {
            $s3Client->putBucketCors([
                'Bucket' => $bucket,
                'CORSConfiguration' => $corsConfig,
            ]);
            
            $this->info('✓ CORS configuration applied successfully!');
            $this->info('Allowed origins:');
            foreach ($corsConfig['CORSRules'][0]['AllowedOrigins'] as $origin) {
                $this->line("  - {$origin}");
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to configure CORS: ' . $e->getMessage());
            $this->error('Please ensure you have the necessary permissions to modify bucket CORS settings.');
            
            return Command::FAILURE;
        }
    }
}