<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Video, HardDrive, Globe, Clock, Upload, FileVideo } from 'lucide-vue-next';

interface VideoStatus {
    completed: number;
    uploading: number;
    processing: number;
    failed: number;
}

interface Stats {
    total_videos: number;
    total_storage: number;
    formatted_storage: string;
    total_duration: number;
    formatted_duration: string;
    public_videos: number;
    videos_by_status: VideoStatus;
}

interface RecentVideo {
    id: number;
    title: string;
    formatted_size: string;
    formatted_duration: string | null;
    status: string;
    is_public: boolean;
    created_at: string;
    created_at_human: string;
}

interface Props {
    stats: Stats;
    recent_videos: RecentVideo[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const getStatusBadgeVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'completed':
            return 'default';
        case 'uploading':
        case 'processing':
            return 'secondary';
        case 'failed':
            return 'destructive';
        default:
            return 'outline';
    }
};

const getStoragePercentage = (): number => {
    // 500GB storage limit
    const limitInBytes = 500 * 1024 * 1024 * 1024; // 500GB
    return Math.min((props.stats.total_storage / limitInBytes) * 100, 100);
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <!-- Total Videos Card -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Videos</CardTitle>
                        <FileVideo class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total_videos }}</div>
                        <div class="flex gap-2 mt-2">
                            <Badge v-if="stats.videos_by_status.completed > 0" variant="default" class="text-xs">
                                {{ stats.videos_by_status.completed }} completed
                            </Badge>
                            <Badge v-if="stats.videos_by_status.uploading > 0" variant="secondary" class="text-xs">
                                {{ stats.videos_by_status.uploading }} uploading
                            </Badge>
                            <Badge v-if="stats.videos_by_status.failed > 0" variant="destructive" class="text-xs">
                                {{ stats.videos_by_status.failed }} failed
                            </Badge>
                        </div>
                    </CardContent>
                </Card>

                <!-- Storage Used Card -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Storage Used</CardTitle>
                        <HardDrive class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.formatted_storage }}</div>
                        <div class="mt-2">
                            <div class="h-2 w-full bg-muted rounded-full overflow-hidden">
                                <div 
                                    class="h-full bg-primary transition-all duration-300"
                                    :style="`width: ${getStoragePercentage()}%`"
                                />
                            </div>
                            <p class="text-xs text-muted-foreground mt-1">
                                {{ getStoragePercentage().toFixed(1) }}% of 500 GB used
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Total Duration Card -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Duration</CardTitle>
                        <Clock class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.formatted_duration }}</div>
                        <p class="text-xs text-muted-foreground mt-1">
                            Total video time
                        </p>
                    </CardContent>
                </Card>

                <!-- Public Videos Card -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Public Videos</CardTitle>
                        <Globe class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.public_videos }}</div>
                        <p class="text-xs text-muted-foreground mt-1">
                            Shared with others
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Recent Videos Section -->
            <Card class="flex-1">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Recent Videos</CardTitle>
                            <CardDescription>Your latest uploaded videos</CardDescription>
                        </div>
                        <div class="flex gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/videos">
                                    View All
                                </Link>
                            </Button>
                            <Button size="sm" asChild>
                                <Link href="/videos/upload">
                                    <Upload class="mr-2 h-4 w-4" />
                                    Upload Video
                                </Link>
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="recent_videos.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
                        <Video class="h-12 w-12 text-muted-foreground mb-4" />
                        <h3 class="text-lg font-medium">No videos yet</h3>
                        <p class="text-sm text-muted-foreground mt-1">
                            Upload your first video to get started
                        </p>
                        <Button class="mt-4" asChild>
                            <Link href="/videos/upload">
                                <Upload class="mr-2 h-4 w-4" />
                                Upload Video
                            </Link>
                        </Button>
                    </div>
                    
                    <Table v-else>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Title</TableHead>
                                <TableHead>Size</TableHead>
                                <TableHead>Duration</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Visibility</TableHead>
                                <TableHead>Uploaded</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="video in recent_videos" :key="video.id">
                                <TableCell class="font-medium">
                                    <Link :href="`/videos/${video.id}`" class="hover:underline">
                                        {{ video.title }}
                                    </Link>
                                </TableCell>
                                <TableCell>{{ video.formatted_size }}</TableCell>
                                <TableCell>{{ video.formatted_duration || '-' }}</TableCell>
                                <TableCell>
                                    <Badge :variant="getStatusBadgeVariant(video.status)">
                                        {{ video.status }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <Badge v-if="video.is_public" variant="outline">
                                        <Globe class="mr-1 h-3 w-3" />
                                        Public
                                    </Badge>
                                    <span v-else class="text-muted-foreground">Private</span>
                                </TableCell>
                                <TableCell>
                                    <span :title="video.created_at">
                                        {{ video.created_at_human }}
                                    </span>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>