<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { home, login, register } from '@/routes';
import { index as documents } from '@/routes/documents';
import { privacy, terms } from '@/routes/legal';

const steps = [
    {
        number: '01',
        title: 'Upload your document',
        text: "Upload a PDF from your device. You'll see a preview before you prepare it.",
    },
    {
        number: '02',
        title: 'Assign the fields',
        text: 'Add signers and drag signature, initials, date, or name fields onto each page.',
    },
    {
        number: '03',
        title: 'Receive the signatures',
        text: 'Each signer draws their signature or uploads an image. The document is marked completed once everyone has signed.',
    },
];

const features = [
    {
        title: 'Drawn or image-based signatures',
        text: 'Draw your signature with your mouse or finger, or upload a photo of it.',
    },
    {
        title: 'Multiple signers',
        text: 'Each signer has their own color, so you always know which field belongs to whom.',
    },
    {
        title: 'Status for every document',
        text: 'Drafts, pending, and completed, all organized in one place.',
    },
];

const documentLines = ['92%', '100%', '84%', '96%'];
</script>

<template>
    <Head title="Electronic Document Signing" />

    <div class="min-h-dvh bg-white">
        <header
            class="mx-auto flex max-w-[1200px] flex-wrap items-center justify-between gap-6 px-[clamp(16px,4vw,32px)] py-4"
        >
            <Link :href="home()" class="flex items-center">
                <AppLogo />
            </Link>

            <nav
                class="hidden items-center gap-7 text-[15px] font-medium desk:flex"
                aria-label="Secciones"
            >
                <a href="#como" class="text-foreground">How it works</a>
                <a href="#funciones" class="text-foreground">Features</a>
            </nav>

            <div class="flex items-center gap-3">
                <template v-if="$page.props.auth.user">
                    <Button as-child>
                        <Link :href="documents()">Go to Documents</Link>
                    </Button>
                </template>
                <template v-else>
                    <Link
                        :href="login()"
                        class="px-2 py-2.5 text-[15px] font-semibold whitespace-nowrap text-foreground"
                    >
                        Log in
                    </Link>
                    <Button as-child class="hidden desk:inline-flex">
                        <Link :href="register()">Create free account</Link>
                    </Button>
                </template>
            </div>
        </header>

        <section
            class="mx-auto grid max-w-[1200px] grid-cols-[repeat(auto-fit,minmax(min(360px,100%),1fr))] items-center gap-[clamp(36px,5vw,56px)] px-[clamp(16px,4vw,32px)] pt-[clamp(24px,5vw,56px)] pb-[clamp(48px,8vw,88px)]"
        >
            <div class="flex flex-col gap-6">
                <span
                    class="text-[13px] font-semibold tracking-[0.08em] text-primary uppercase"
                >
                    Electronic document signing
                </span>
                <h1
                    class="text-[clamp(36px,9vw,56px)] leading-[1.08] font-semibold tracking-[-0.02em] text-balance"
                >
                    Sign and send documents in minutes.
                </h1>
                <p
                    class="max-w-[520px] text-[clamp(16px,2.4vw,18px)] leading-[1.6] text-pretty text-muted-foreground"
                >
                    Upload a PDF, drag the signature fields wherever you need
                    them, and send it. Your signers can sign from any device,
                    drawing their signature or uploading an image.
                </p>
                <div class="mt-2 flex flex-wrap gap-3">
                    <Button as-child size="lg" class="flex-auto rounded-xl">
                        <Link :href="register()">Get started free</Link>
                    </Button>
                    <Button
                        as-child
                        variant="outline"
                        size="lg"
                        class="flex-auto rounded-xl border-[1.5px] border-input bg-white text-foreground hover:border-foreground hover:bg-white hover:text-foreground"
                    >
                        <Link :href="documents()">Try the editor</Link>
                    </Button>
                </div>
            </div>

            <div
                class="relative flex min-h-[clamp(380px,50vw,440px)] items-center justify-center rounded-3xl bg-secondary px-[clamp(16px,4vw,40px)] py-[clamp(72px,10vw,48px)]"
            >
                <div
                    class="flex w-full max-w-[360px] flex-col gap-2.5 rounded-[10px] bg-white px-[30px] py-8 shadow-[0_20px_50px_rgba(26,53,96,0.14)]"
                >
                    <div class="font-display text-[15px] font-semibold">
                        Service agreement
                    </div>
                    <div
                        v-for="width in documentLines"
                        :key="width"
                        class="h-[7px] rounded bg-[#e6eaef]"
                        :style="{ width }"
                    />
                    <div class="mb-[18px] h-[7px] w-[62%] rounded bg-[#e6eaef]" />
                    <div class="flex gap-3.5">
                        <div
                            class="flex h-[58px] flex-1 items-center justify-center rounded-md border-[1.5px] border-dashed border-primary bg-primary/10 text-[13px] font-semibold text-primary"
                        >
                            Sign here
                        </div>
                        <div
                            class="flex h-[58px] w-24 items-center justify-center rounded-md border-[1.5px] border-dashed border-[#7a4fc9] bg-[#7a4fc9]/10 text-[13px] font-semibold text-[#7a4fc9]"
                        >
                            Date
                        </div>
                    </div>
                </div>

                <div
                    class="absolute right-[clamp(12px,3vw,24px)] bottom-[clamp(16px,3vw,36px)] flex items-center gap-2.5 rounded-xl bg-white px-4 py-3 shadow-[0_10px_30px_rgba(26,53,96,0.16)]"
                >
                    <div
                        class="flex size-[30px] items-center justify-center rounded-full bg-primary text-[13px] font-bold text-white"
                    >
                        C
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-[13px] font-semibold"
                            >Carlos Ruiz signed</span
                        >
                        <span class="text-xs text-muted-foreground"
                            >2 minutes ago</span
                        >
                    </div>
                </div>

                <div
                    class="absolute top-[clamp(16px,3vw,32px)] left-[clamp(12px,3vw,24px)] rounded-full bg-foreground px-3.5 py-2 text-xs font-semibold text-white"
                >
                    2 of 3 signatures
                </div>
            </div>
        </section>

        <section
            id="como"
            class="bg-muted px-[clamp(16px,4vw,32px)] py-[clamp(56px,9vw,88px)]"
        >
            <div class="mx-auto flex max-w-[1200px] flex-col gap-12">
                <h2
                    class="text-[clamp(28px,6vw,38px)] font-semibold tracking-[-0.02em]"
                >
                    How it works
                </h2>
                <div
                    class="grid grid-cols-[repeat(auto-fit,minmax(260px,1fr))] gap-8"
                >
                    <div
                        v-for="step in steps"
                        :key="step.number"
                        class="flex flex-col gap-3"
                    >
                        <span
                            class="font-display text-[15px] font-semibold text-primary"
                            >{{ step.number }}</span
                        >
                        <h3 class="text-[21px] font-semibold">
                            {{ step.title }}
                        </h3>
                        <p class="text-[15px] leading-[1.6] text-muted-foreground">
                            {{ step.text }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section
            id="funciones"
            class="px-[clamp(16px,4vw,32px)] py-[clamp(56px,9vw,88px)]"
        >
            <div
                class="mx-auto grid max-w-[1200px] grid-cols-[repeat(auto-fit,minmax(260px,1fr))] gap-10"
            >
                <div
                    v-for="feature in features"
                    :key="feature.title"
                    class="flex flex-col gap-2.5 border-t-2 border-foreground pt-5"
                >
                    <h3 class="text-[19px] font-semibold">
                        {{ feature.title }}
                    </h3>
                    <p class="text-[15px] leading-[1.6] text-muted-foreground">
                        {{ feature.text }}
                    </p>
                </div>
            </div>
        </section>

        <section
            class="px-[clamp(16px,4vw,32px)] pb-[clamp(56px,9vw,88px)]"
        >
            <div
                class="mx-auto flex max-w-[1200px] flex-wrap items-center justify-between gap-8 rounded-3xl bg-foreground px-[clamp(24px,5vw,48px)] py-[clamp(36px,7vw,64px)]"
            >
                <h2
                    class="max-w-[560px] text-[clamp(26px,5.5vw,34px)] font-semibold tracking-[-0.02em] text-white"
                >
                    Your first signed document today.
                </h2>
                <Button as-child size="lg" class="rounded-xl">
                    <Link :href="register()">Create free account</Link>
                </Button>
            </div>
        </section>

        <footer class="border-t border-border px-[clamp(16px,4vw,32px)] py-7">
            <div
                class="mx-auto flex max-w-[1200px] flex-wrap items-center justify-between gap-4 text-[13px] text-muted-foreground"
            >
                <div class="flex items-center gap-2">
                    <img src="/img/icon.svg" alt="" class="h-[22px] w-auto" />
                    <span
                        class="font-display text-[15px] font-semibold text-foreground"
                        >Ravsign</span
                    >
                </div>
                <div class="flex items-center gap-5">
                    <Link :href="terms()" class="hover:text-foreground"
                        >Terms of Service</Link
                    >
                    <Link :href="privacy()" class="hover:text-foreground"
                        >Privacy Policy</Link
                    >
                    <span>© 2026 Ravsign · Secure Document Signing</span>
                </div>
            </div>
        </footer>
    </div>
</template>
