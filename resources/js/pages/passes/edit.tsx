import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import * as passes from '@/routes/passes';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { Apple, ArrowLeft, Chrome } from 'lucide-react';
import {
    type Pass,
    type PassField,
    type PassImageSlot,
    type PassImages,
    type PassImageUploadResult,
    type PassPlatform,
} from '@/types/pass';
import { PassPreview } from '@/components/pass-preview';
import { PassFieldEditor } from '@/components/pass-field-editor';
import { ColorPicker } from '@/components/color-picker';
import { ImageUploader } from '@/components/image-uploader';
import {
    SampleOverwriteDialog,
    SamplePicker,
} from '@/components/sample-picker';
import {
    applyPassImageUpload,
    getVariantPreviewUrl,
    getVariantQualityWarning,
    normalizePassImages,
    removePassImageSlot,
} from '@/lib/pass-images';
import {
    buildImagesFromSample,
    collectSampleImagePayload,
    createSample,
    hasAllSampleImageSlots,
    listSamples,
} from '@/lib/samples';
import {
    fetchPassTypeFieldMap,
    requiresTransitType,
    shouldShowFieldGroup,
    type PassTypeFieldMap,
} from '@/lib/pass-type-fields';
import { cn } from '@/lib/utils';
import type { PassTypeSample } from '@/types/sample';

interface PassesEditProps {
    pass: Pass;
}

const transitTypes = [
    { value: 'PKTransitTypeAir', label: 'Air' },
    { value: 'PKTransitTypeTrain', label: 'Train' },
    { value: 'PKTransitTypeBus', label: 'Bus' },
    { value: 'PKTransitTypeBoat', label: 'Boat' },
    { value: 'PKTransitTypeGeneric', label: 'Generic' },
];

const barcodeFormats = [
    { value: 'PKBarcodeFormatQR', label: 'QR Code' },
    { value: 'PKBarcodeFormatPDF417', label: 'PDF417' },
    { value: 'PKBarcodeFormatAztec', label: 'Aztec' },
    { value: 'PKBarcodeFormatCode128', label: 'Code 128' },
];

export default function PassesEdit({ pass }: PassesEditProps) {
    const [previewPlatform, setPreviewPlatform] = useState<PassPlatform>(
        pass.platforms[0] ?? 'apple',
    );
    const [samplePickerOpen, setSamplePickerOpen] = useState(false);
    const [sampleConfirmOpen, setSampleConfirmOpen] = useState(false);
    const [samples, setSamples] = useState<PassTypeSample[]>([]);
    const [samplesLoading, setSamplesLoading] = useState(false);
    const [samplesError, setSamplesError] = useState<string | null>(null);
    const [pendingSample, setPendingSample] = useState<PassTypeSample | null>(
        null,
    );
    const [fieldMap, setFieldMap] = useState<PassTypeFieldMap | null>(null);
    const { data, setData, put, processing, errors } = useForm({
        pass_data: pass.pass_data,
        barcode_data: pass.barcode_data || {
            message: '',
            format: 'PKBarcodeFormatQR',
            altText: '',
        },
        has_barcode: !!pass.barcode_data?.message,
        images: normalizePassImages(
            pass.images ?? {},
            pass.platforms[0] ?? 'apple',
        ) as PassImages,
    });

    const uploadPlatform = previewPlatform;
    const normalizedImages = normalizePassImages(
        data.images as PassImages,
        uploadPlatform,
    );

    useEffect(() => {
        const fetchSamples = async () => {
            try {
                setSamplesLoading(true);
                setSamplesError(null);
                const response = await listSamples({
                    pass_type: pass.pass_type,
                    platform: previewPlatform,
                    source: 'all',
                });
                const items = Array.isArray(response)
                    ? response
                    : response.data;
                setSamples(items ?? []);
            } catch (error) {
                console.error(error);
                setSamplesError('Unable to load samples.');
            } finally {
                setSamplesLoading(false);
            }
        };

        fetchSamples();
    }, [previewPlatform, pass.pass_type]);

    useEffect(() => {
        const fetchFieldMap = async () => {
            try {
                const map = await fetchPassTypeFieldMap(
                    pass.pass_type,
                    previewPlatform,
                );
                setFieldMap(map);
            } catch (error) {
                console.error(error);
                setFieldMap(null);
            }
        };

        fetchFieldMap();
    }, [previewPlatform, pass.pass_type]);

    const handleImageUpload =
        (slot: PassImageSlot) => (result: PassImageUploadResult) => {
            const nextImages = applyPassImageUpload(
                normalizePassImages(data.images as PassImages, uploadPlatform),
                uploadPlatform,
                slot,
                result,
            );

            setData('images', nextImages);
        };

    const handleImageRemove = (slot: PassImageSlot) => () => {
        const nextImages = removePassImageSlot(
            normalizePassImages(data.images as PassImages, uploadPlatform),
            uploadPlatform,
            slot,
        );

        setData('images', nextImages);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(passes.update({ pass: pass.id }).url);
    };

    const hasSampleEdits = () => {
        const hasFields =
            data.pass_data.description ||
            data.pass_data.organizationName ||
            data.pass_data.logoText ||
            data.pass_data.headerFields.length > 0 ||
            data.pass_data.primaryFields.length > 0 ||
            data.pass_data.secondaryFields.length > 0 ||
            data.pass_data.auxiliaryFields.length > 0 ||
            data.pass_data.backFields.length > 0 ||
            data.pass_data.transitType;

        const hasImages =
            Object.keys(normalizedImages.originals ?? {}).length > 0 ||
            Object.keys(normalizedImages.variants ?? {}).length > 0;

        return Boolean(hasFields || hasImages);
    };

    const applySample = (sample: PassTypeSample) => {
        const nextImages = buildImagesFromSample(sample, previewPlatform);
        setData({
            ...data,
            pass_data: {
                ...data.pass_data,
                ...sample.fields,
            },
            images: nextImages,
        });
        setSamplePickerOpen(false);
    };

    const handleSampleSelect = (sample: PassTypeSample) => {
        if (hasSampleEdits()) {
            setPendingSample(sample);
            setSampleConfirmOpen(true);
            return;
        }

        applySample(sample);
    };

    const handleSampleConfirm = () => {
        if (pendingSample) {
            applySample(pendingSample);
        }
        setPendingSample(null);
        setSampleConfirmOpen(false);
    };

    const handleSampleCancel = () => {
        setPendingSample(null);
        setSampleConfirmOpen(false);
    };

    const handleSampleSave = async () => {
        const imagePayload = collectSampleImagePayload(
            normalizedImages,
            previewPlatform,
        );
        if (!hasAllSampleImageSlots(imagePayload)) {
            alert('Please provide all image slots before saving a sample.');
            return;
        }

        try {
            await createSample({
                name: data.pass_data.description
                    ? `Sample - ${data.pass_data.description}`
                    : 'New Sample',
                description: data.pass_data.description || null,
                pass_type: pass.pass_type,
                platform: previewPlatform,
                fields: data.pass_data,
                images: imagePayload as Record<PassImageSlot, string>,
            });
            const response = await listSamples({
                pass_type: pass.pass_type,
                platform: previewPlatform,
                source: 'all',
            });
            const items = Array.isArray(response) ? response : response.data;
            setSamples(items ?? []);
        } catch (error) {
            console.error(error);
            alert('Unable to save sample.');
        }
    };

    return (
        <AppLayout
            title="Edit Pass"
            header={
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={passes.show({ pass: pass.id }).url}>
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back
                        </Link>
                    </Button>
                    <div>
                        <h2 className="text-xl font-semibold">Edit Pass</h2>
                        <p className="text-sm text-muted-foreground">
                            {pass.pass_data.description || 'Untitled Pass'}
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="Edit Pass" />

            <form onSubmit={handleSubmit} className="mx-auto max-w-5xl">
                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Left Column: Form */}
                    <div className="space-y-6">
                        {/* Platform & Type (Read-only) */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Pass Type</CardTitle>
                                <CardDescription>
                                    Platform and type cannot be changed after
                                    creation
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <Label>Platforms</Label>
                                    <div className="mt-2 flex items-center gap-2">
                                        {pass.platforms.includes('apple') && (
                                            <div className="flex items-center gap-1">
                                                <Apple className="h-4 w-4" />
                                                <span>Apple Wallet</span>
                                            </div>
                                        )}
                                        {pass.platforms.length === 2 && (
                                            <span className="text-muted-foreground">
                                                +
                                            </span>
                                        )}
                                        {pass.platforms.includes('google') && (
                                            <div className="flex items-center gap-1">
                                                <Chrome className="h-4 w-4" />
                                                <span>Google Wallet</span>
                                            </div>
                                        )}
                                        <Badge
                                            variant="secondary"
                                            className="ml-2"
                                        >
                                            Read-only
                                        </Badge>
                                    </div>
                                </div>

                                <div>
                                    <Label>Pass Type</Label>
                                    <p className="mt-2 capitalize">
                                        {pass.pass_type
                                            .replace(/([A-Z])/g, ' $1')
                                            .trim()}
                                        <Badge
                                            variant="secondary"
                                            className="ml-2"
                                        >
                                            Read-only
                                        </Badge>
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Basic Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Basic Information</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="description">
                                        Description *
                                    </Label>
                                    <Input
                                        id="description"
                                        value={data.pass_data.description}
                                        onChange={(e) =>
                                            setData('pass_data', {
                                                ...data.pass_data,
                                                description: e.target.value,
                                            })
                                        }
                                        placeholder="Concert Ticket"
                                    />
                                    {errors['pass_data.description'] && (
                                        <p className="text-sm text-destructive">
                                            {errors['pass_data.description']}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="organizationName">
                                        Organization Name *
                                    </Label>
                                    <Input
                                        id="organizationName"
                                        value={data.pass_data.organizationName}
                                        onChange={(e) =>
                                            setData('pass_data', {
                                                ...data.pass_data,
                                                organizationName:
                                                    e.target.value,
                                            })
                                        }
                                        placeholder="Acme Inc."
                                    />
                                    {errors['pass_data.organizationName'] && (
                                        <p className="text-sm text-destructive">
                                            {
                                                errors[
                                                    'pass_data.organizationName'
                                                ]
                                            }
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="logoText">Logo Text</Label>
                                    <Input
                                        id="logoText"
                                        value={data.pass_data.logoText}
                                        onChange={(e) =>
                                            setData('pass_data', {
                                                ...data.pass_data,
                                                logoText: e.target.value,
                                            })
                                        }
                                        placeholder="ACME"
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Samples */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Pass Type Sample</CardTitle>
                                <CardDescription>
                                    Pick a ready sample to pre-fill fields and
                                    images.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setSamplePickerOpen(true)}
                                >
                                    Choose Sample
                                </Button>
                                {samplesError && (
                                    <p className="text-sm text-destructive">
                                        {samplesError}
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {/* Colors */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Colors</CardTitle>
                                <CardDescription>
                                    Customize your pass appearance
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <ColorPicker
                                    label="Background Color"
                                    value={data.pass_data.backgroundColor}
                                    onChange={(color) =>
                                        setData('pass_data', {
                                            ...data.pass_data,
                                            backgroundColor: color,
                                        })
                                    }
                                />
                                <ColorPicker
                                    label="Foreground Color"
                                    value={data.pass_data.foregroundColor}
                                    onChange={(color) =>
                                        setData('pass_data', {
                                            ...data.pass_data,
                                            foregroundColor: color,
                                        })
                                    }
                                />
                                <ColorPicker
                                    label="Label Color"
                                    value={data.pass_data.labelColor}
                                    onChange={(color) =>
                                        setData('pass_data', {
                                            ...data.pass_data,
                                            labelColor: color,
                                        })
                                    }
                                />
                            </CardContent>
                        </Card>

                        {/* Pass Fields */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Pass Fields</CardTitle>
                                <CardDescription>
                                    Manage content displayed on your pass
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                {shouldShowFieldGroup(fieldMap, 'header') && (
                                    <div className="space-y-3">
                                        <Label>Header Fields</Label>
                                        <PassFieldEditor
                                            fields={data.pass_data.headerFields}
                                            onChange={(fields) =>
                                                setData('pass_data', {
                                                    ...data.pass_data,
                                                    headerFields: fields,
                                                })
                                            }
                                            maxFields={3}
                                        />
                                    </div>
                                )}

                                {shouldShowFieldGroup(fieldMap, 'primary') && (
                                    <div className="space-y-3">
                                        <Label>Primary Fields</Label>
                                        <PassFieldEditor
                                            fields={
                                                data.pass_data.primaryFields
                                            }
                                            onChange={(fields) =>
                                                setData('pass_data', {
                                                    ...data.pass_data,
                                                    primaryFields: fields,
                                                })
                                            }
                                            maxFields={3}
                                        />
                                    </div>
                                )}

                                {shouldShowFieldGroup(
                                    fieldMap,
                                    'secondary',
                                ) && (
                                    <div className="space-y-3">
                                        <Label>Secondary Fields</Label>
                                        <PassFieldEditor
                                            fields={
                                                data.pass_data.secondaryFields
                                            }
                                            onChange={(fields) =>
                                                setData('pass_data', {
                                                    ...data.pass_data,
                                                    secondaryFields: fields,
                                                })
                                            }
                                            maxFields={4}
                                        />
                                    </div>
                                )}

                                {shouldShowFieldGroup(
                                    fieldMap,
                                    'auxiliary',
                                ) && (
                                    <div className="space-y-3">
                                        <Label>Auxiliary Fields</Label>
                                        <PassFieldEditor
                                            fields={
                                                data.pass_data.auxiliaryFields
                                            }
                                            onChange={(fields) =>
                                                setData('pass_data', {
                                                    ...data.pass_data,
                                                    auxiliaryFields: fields,
                                                })
                                            }
                                            maxFields={4}
                                        />
                                    </div>
                                )}

                                {shouldShowFieldGroup(fieldMap, 'back') && (
                                    <div className="space-y-3">
                                        <Label>Back Fields</Label>
                                        <PassFieldEditor
                                            fields={data.pass_data.backFields}
                                            onChange={(fields) =>
                                                setData('pass_data', {
                                                    ...data.pass_data,
                                                    backFields: fields,
                                                })
                                            }
                                        />
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Transit Type for Boarding Passes */}
                        {requiresTransitType(fieldMap) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Transit Type</CardTitle>
                                    <CardDescription>
                                        Specify the type of transportation
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Select
                                        value={data.pass_data.transitType}
                                        onValueChange={(value) =>
                                            setData('pass_data', {
                                                ...data.pass_data,
                                                transitType: value,
                                            })
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select transit type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {transitTypes.map((type) => (
                                                <SelectItem
                                                    key={type.value}
                                                    value={type.value}
                                                >
                                                    {type.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </CardContent>
                            </Card>
                        )}

                        {/* Barcode */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Barcode Configuration</CardTitle>
                                <CardDescription>
                                    Add a scannable barcode to your pass
                                    (optional)
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="flex items-center justify-between">
                                    <div className="space-y-0.5">
                                        <Label htmlFor="has-barcode">
                                            Enable Barcode
                                        </Label>
                                        <p className="text-sm text-muted-foreground">
                                            Add a QR code or other barcode
                                            format
                                        </p>
                                    </div>
                                    <Switch
                                        id="has-barcode"
                                        checked={data.has_barcode}
                                        onCheckedChange={(checked) =>
                                            setData('has_barcode', checked)
                                        }
                                    />
                                </div>

                                {data.has_barcode && (
                                    <div className="space-y-4 border-t pt-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="barcode-format">
                                                Barcode Format
                                            </Label>
                                            <Select
                                                value={data.barcode_data.format}
                                                onValueChange={(value) =>
                                                    setData('barcode_data', {
                                                        ...data.barcode_data,
                                                        format: value,
                                                    })
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {barcodeFormats.map(
                                                        (format) => (
                                                            <SelectItem
                                                                key={
                                                                    format.value
                                                                }
                                                                value={
                                                                    format.value
                                                                }
                                                            >
                                                                {format.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="barcode-message">
                                                Barcode Message *
                                            </Label>
                                            <Input
                                                id="barcode-message"
                                                value={
                                                    data.barcode_data.message
                                                }
                                                onChange={(e) =>
                                                    setData('barcode_data', {
                                                        ...data.barcode_data,
                                                        message: e.target.value,
                                                    })
                                                }
                                                placeholder="123456789"
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                The data encoded in the barcode
                                            </p>
                                            {errors['barcode_data.message'] && (
                                                <p className="text-sm text-destructive">
                                                    {
                                                        errors[
                                                            'barcode_data.message'
                                                        ]
                                                    }
                                                </p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="barcode-altText">
                                                Alternative Text
                                            </Label>
                                            <Input
                                                id="barcode-altText"
                                                value={
                                                    data.barcode_data.altText
                                                }
                                                onChange={(e) =>
                                                    setData('barcode_data', {
                                                        ...data.barcode_data,
                                                        altText: e.target.value,
                                                    })
                                                }
                                                placeholder="Order #123456789"
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                Text displayed near the barcode
                                            </p>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Images */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Pass Images</CardTitle>
                                <CardDescription>
                                    Update images for your pass. We will resize
                                    with transparent padding for the selected
                                    platform.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-6 md:grid-cols-2">
                                    <ImageUploader
                                        label="Icon"
                                        description="Required for Apple Wallet"
                                        slot="icon"
                                        platform={uploadPlatform}
                                        value={getVariantPreviewUrl(
                                            normalizedImages,
                                            uploadPlatform,
                                            'icon',
                                        )}
                                        qualityWarning={getVariantQualityWarning(
                                            normalizedImages,
                                            uploadPlatform,
                                            'icon',
                                        )}
                                        onUpload={handleImageUpload('icon')}
                                        onRemove={handleImageRemove('icon')}
                                    />
                                    <ImageUploader
                                        label="Logo"
                                        description="Appears near the top of the pass"
                                        slot="logo"
                                        platform={uploadPlatform}
                                        value={getVariantPreviewUrl(
                                            normalizedImages,
                                            uploadPlatform,
                                            'logo',
                                        )}
                                        qualityWarning={getVariantQualityWarning(
                                            normalizedImages,
                                            uploadPlatform,
                                            'logo',
                                        )}
                                        onUpload={handleImageUpload('logo')}
                                        onRemove={handleImageRemove('logo')}
                                    />
                                    <ImageUploader
                                        label="Background"
                                        description="Optional background image"
                                        slot="background"
                                        platform={uploadPlatform}
                                        value={getVariantPreviewUrl(
                                            normalizedImages,
                                            uploadPlatform,
                                            'background',
                                        )}
                                        qualityWarning={getVariantQualityWarning(
                                            normalizedImages,
                                            uploadPlatform,
                                            'background',
                                        )}
                                        onUpload={handleImageUpload(
                                            'background',
                                        )}
                                        onRemove={handleImageRemove(
                                            'background',
                                        )}
                                    />
                                    <ImageUploader
                                        label="Strip"
                                        description="Event/coupon passes"
                                        slot="strip"
                                        platform={uploadPlatform}
                                        value={getVariantPreviewUrl(
                                            normalizedImages,
                                            uploadPlatform,
                                            'strip',
                                        )}
                                        qualityWarning={getVariantQualityWarning(
                                            normalizedImages,
                                            uploadPlatform,
                                            'strip',
                                        )}
                                        onUpload={handleImageUpload('strip')}
                                        onRemove={handleImageRemove('strip')}
                                    />
                                    <ImageUploader
                                        label="Thumbnail"
                                        description="Small square image"
                                        slot="thumbnail"
                                        platform={uploadPlatform}
                                        value={getVariantPreviewUrl(
                                            normalizedImages,
                                            uploadPlatform,
                                            'thumbnail',
                                        )}
                                        qualityWarning={getVariantQualityWarning(
                                            normalizedImages,
                                            uploadPlatform,
                                            'thumbnail',
                                        )}
                                        onUpload={handleImageUpload(
                                            'thumbnail',
                                        )}
                                        onRemove={handleImageRemove(
                                            'thumbnail',
                                        )}
                                    />
                                    <ImageUploader
                                        label="Footer"
                                        description="Optional footer image"
                                        slot="footer"
                                        platform={uploadPlatform}
                                        value={getVariantPreviewUrl(
                                            normalizedImages,
                                            uploadPlatform,
                                            'footer',
                                        )}
                                        qualityWarning={getVariantQualityWarning(
                                            normalizedImages,
                                            uploadPlatform,
                                            'footer',
                                        )}
                                        onUpload={handleImageUpload('footer')}
                                        onRemove={handleImageRemove('footer')}
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right Column: Preview */}
                    <div className="space-y-6 lg:sticky lg:top-6 lg:h-fit">
                        <Card>
                            <CardHeader>
                                <CardTitle>Live Preview</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {pass.platforms.length > 1 && (
                                    <ToggleGroup
                                        type="single"
                                        value={previewPlatform}
                                        onValueChange={(value) => {
                                            if (value) {
                                                setPreviewPlatform(
                                                    value as PassPlatform,
                                                );
                                            }
                                        }}
                                        className="justify-start"
                                    >
                                        {pass.platforms.includes('apple') && (
                                            <ToggleGroupItem
                                                value="apple"
                                                aria-label="Apple Wallet preview"
                                            >
                                                <Apple className="h-4 w-4" />
                                            </ToggleGroupItem>
                                        )}
                                        {pass.platforms.includes('google') && (
                                            <ToggleGroupItem
                                                value="google"
                                                aria-label="Google Wallet preview"
                                            >
                                                <Chrome className="h-4 w-4" />
                                            </ToggleGroupItem>
                                        )}
                                    </ToggleGroup>
                                )}
                                <PassPreview
                                    passData={data.pass_data}
                                    barcodeData={
                                        data.has_barcode
                                            ? data.barcode_data
                                            : undefined
                                    }
                                    platform={previewPlatform}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="space-y-3 pt-6">
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={processing}
                                >
                                    {processing ? 'Saving...' : 'Save Changes'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="w-full"
                                    asChild
                                >
                                    <Link
                                        href={
                                            passes.show({ pass: pass.id }).url
                                        }
                                    >
                                        Cancel
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </form>

            <SamplePicker
                open={samplePickerOpen}
                onOpenChange={setSamplePickerOpen}
                title="Choose a sample"
                description="Samples pre-fill fields and images for this pass type."
                footer={
                    <Button
                        type="button"
                        variant="outline"
                        onClick={handleSampleSave}
                    >
                        Save current as sample
                    </Button>
                }
            >
                <div className="space-y-3">
                    {samplesLoading ? (
                        <p className="text-sm text-muted-foreground">
                            Loading samples…
                        </p>
                    ) : samples.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No samples available yet. You can continue without a
                            sample.
                        </p>
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-2">
                            {samples.map((sample) => (
                                <button
                                    key={sample.id}
                                    type="button"
                                    className={cn(
                                        'rounded-lg border p-3 text-left transition-colors hover:border-primary',
                                    )}
                                    onClick={() => handleSampleSelect(sample)}
                                >
                                    <p className="text-sm font-medium">
                                        {sample.name}
                                    </p>
                                    {sample.description && (
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {sample.description}
                                        </p>
                                    )}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            </SamplePicker>

            <SampleOverwriteDialog
                open={sampleConfirmOpen}
                message="Applying a sample will replace your current fields and images."
                onCancel={handleSampleCancel}
                onConfirm={handleSampleConfirm}
            />
        </AppLayout>
    );
}
