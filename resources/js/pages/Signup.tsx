import { useMemo, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes';
import { INDUSTRY_OPTIONS } from '@/lib/industry-options';

interface SignupFormData {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    region: 'EU' | 'US' | '';
    industry: string;
    agree_terms: boolean;
}

interface SignupResponse {
    user: {
        id: number;
        email: string;
        name: string;
        approval_status: 'pending' | 'approved' | 'rejected';
        tier: string;
    };
    message: string;
}

export default function Signup() {
    const { flash } = usePage().props;
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [formData, setFormData] = useState<SignupFormData>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        region: '',
        industry: '',
        agree_terms: false,
    });
    const [submitSuccess, setSubmitSuccess] = useState(false);
    const [successMessage, setSuccessMessage] = useState('');
    const [approvalStatus, setApprovalStatus] = useState<
        'pending' | 'approved' | null
    >(null);

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const { name, value, type, checked } = e.target;
        setFormData((prev) => ({
            ...prev,
            [name]: type === 'checkbox' ? checked : value,
        }));
        // Clear error for this field when user starts typing
        if (errors[name]) {
            setErrors((prev) => {
                const newErrors = { ...prev };
                delete newErrors[name];
                return newErrors;
            });
        }
    };

    const handleRegionChange = (value: string) => {
        setFormData((prev) => ({
            ...prev,
            region: value as 'EU' | 'US',
        }));
        if (errors.region) {
            setErrors((prev) => {
                const newErrors = { ...prev };
                delete newErrors.region;
                return newErrors;
            });
        }
    };

    const handleIndustryChange = (value: string) => {
        setFormData((prev) => ({
            ...prev,
            industry: value,
        }));
        if (errors.industry) {
            setErrors((prev) => {
                const newErrors = { ...prev };
                delete newErrors.industry;
                return newErrors;
            });
        }
    };

    const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            const response = await fetch('/api/signup', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: JSON.stringify(formData),
            });

            const data: SignupResponse = await response.json();

            if (!response.ok) {
                if (response.status === 422) {
                    // Validation errors
                    const errorData = data as any;
                    setErrors(errorData.errors || {});
                } else {
                    setErrors({
                        submit:
                            data.message || 'An error occurred during signup.',
                    });
                }
            } else {
                // Success
                setSubmitSuccess(true);
                setApprovalStatus(data.user.approval_status);
                if (data.user.approval_status === 'approved') {
                    setSuccessMessage(
                        "Account created! You're all set. Log in to get started.",
                    );
                } else {
                    setSuccessMessage(
                        "Thanks for signing up! Your account is pending approval. We'll email you within 24 hours.",
                    );
                }
                // Redirect to login after 2 seconds
                setTimeout(() => {
                    router.visit(login());
                }, 2000);
            }
        } catch (error) {
            console.error('Signup error:', error);
            setErrors({
                submit: 'An unexpected error occurred. Please try again.',
            });
        } finally {
            setProcessing(false);
        }
    };

    if (submitSuccess) {
        return (
            <AuthLayout title="Signup Successful" description={successMessage}>
                <Head title="Signup Successful" />
                <div className="flex flex-col items-center gap-4">
                    <div className="text-center">
                        <h2 className="text-lg font-semibold text-foreground">
                            {approvalStatus === 'approved'
                                ? '🎉 Account Created!'
                                : '⏳ Pending Approval'}
                        </h2>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {successMessage}
                        </p>
                    </div>
                    <div className="text-xs text-muted-foreground">
                        Redirecting to login in 2 seconds...
                    </div>
                </div>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout
            title="Create your account"
            description="Sign up to start creating passes"
        >
            <Head title="Sign Up" />
            <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                <div className="grid gap-6">
                    {/* Name Field */}
                    <div className="grid gap-2">
                        <Label htmlFor="name">Full Name</Label>
                        <Input
                            id="name"
                            type="text"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="name"
                            name="name"
                            placeholder="John Doe"
                            value={formData.name}
                            onChange={handleInputChange}
                            disabled={processing}
                        />
                        <InputError message={errors.name} className="mt-2" />
                    </div>

                    {/* Email Field */}
                    <div className="grid gap-2">
                        <Label htmlFor="email">Email Address</Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            tabIndex={2}
                            autoComplete="email"
                            name="email"
                            placeholder="email@example.com"
                            value={formData.email}
                            onChange={handleInputChange}
                            disabled={processing}
                        />
                        <InputError message={errors.email} />
                    </div>

                    {/* Region Field */}
                    <div className="grid gap-2">
                        <Label htmlFor="region">Data Region</Label>
                        <Select
                            value={formData.region}
                            onValueChange={handleRegionChange}
                            disabled={processing}
                        >
                            <SelectTrigger id="region">
                                <SelectValue placeholder="Select a region..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="EU">Europe (EU)</SelectItem>
                                <SelectItem value="US">
                                    United States (US)
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p className="text-xs text-muted-foreground">
                            This cannot be changed after signup
                        </p>
                        <InputError message={errors.region} className="mt-2" />
                    </div>

                    {/* Industry Field */}
                    <div className="grid gap-2">
                        <Label htmlFor="industry">Industry (Optional)</Label>
                        <Select
                            value={formData.industry}
                            onValueChange={handleIndustryChange}
                            disabled={processing}
                        >
                            <SelectTrigger id="industry">
                                <SelectValue placeholder="Select an industry..." />
                            </SelectTrigger>
                            <SelectContent>
                                {INDUSTRY_OPTIONS.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            message={errors.industry}
                            className="mt-2"
                        />
                    </div>

                    {/* Password Field */}
                    <div className="grid gap-2">
                        <Label htmlFor="password">Password</Label>
                        <Input
                            id="password"
                            type="password"
                            required
                            tabIndex={3}
                            autoComplete="new-password"
                            name="password"
                            placeholder="Minimum 8 characters"
                            value={formData.password}
                            onChange={handleInputChange}
                            disabled={processing}
                        />
                        <p className="text-xs text-muted-foreground">
                            Must contain letters, numbers, and symbols
                        </p>
                        <InputError
                            message={errors.password}
                            className="mt-2"
                        />
                    </div>

                    {/* Password Confirmation Field */}
                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">
                            Confirm Password
                        </Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            required
                            tabIndex={4}
                            autoComplete="new-password"
                            name="password_confirmation"
                            placeholder="Confirm password"
                            value={formData.password_confirmation}
                            onChange={handleInputChange}
                            disabled={processing}
                        />
                        <InputError
                            message={errors.password_confirmation}
                            className="mt-2"
                        />
                    </div>

                    {/* Terms Checkbox */}
                    <div className="flex items-start gap-2">
                        <Checkbox
                            id="agree_terms"
                            name="agree_terms"
                            checked={formData.agree_terms}
                            onCheckedChange={(checked) =>
                                setFormData((prev) => ({
                                    ...prev,
                                    agree_terms: checked as boolean,
                                }))
                            }
                            disabled={processing}
                            tabIndex={5}
                            required
                        />
                        <Label
                            htmlFor="agree_terms"
                            className="cursor-pointer text-sm leading-relaxed"
                        >
                            I agree to the{' '}
                            <TextLink href="#">Terms of Service</TextLink> and{' '}
                            <TextLink href="#">Privacy Policy</TextLink>
                        </Label>
                    </div>
                    <InputError message={errors.agree_terms} className="mt-2" />

                    {/* Submit Button */}
                    <Button
                        type="submit"
                        className="mt-2 w-full"
                        tabIndex={6}
                        disabled={processing}
                        data-test="signup-button"
                    >
                        {processing && <Spinner className="mr-2" />}
                        {processing ? 'Creating account...' : 'Create account'}
                    </Button>

                    {/* Error Messages */}
                    {errors.submit && (
                        <div className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                            {errors.submit}
                        </div>
                    )}
                </div>

                {/* Login Link */}
                <div className="text-center text-sm text-muted-foreground">
                    Already have an account?{' '}
                    <TextLink href={login()} tabIndex={7}>
                        Log in
                    </TextLink>
                </div>
            </form>
        </AuthLayout>
    );
}
