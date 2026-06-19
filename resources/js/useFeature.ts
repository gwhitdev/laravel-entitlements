import { usePage } from '@inertiajs/react';

export default function useFeature() {
    const features = (usePage().props.features as string[]) ?? [];

    return (key: string): boolean => features.includes(key);
}
