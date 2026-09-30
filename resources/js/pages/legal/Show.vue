<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';
import { privacy, terms } from '@/routes/legal';

const props = defineProps<{
    type: 'terms' | 'privacy';
}>();

const TERMS_SECTIONS = [
    {
        title: 'Using the Service',
        body: 'You may use Ravsign to prepare documents, add signers, and send them a signing link. You are responsible for having the right to share the documents you upload and for making sure the signers you add are the correct people.',
    },
    {
        title: 'Your Account',
        body: 'You must provide a valid name and email address. Keep your password secret; actions taken from your account are considered to have been taken by you.',
    },
    {
        title: 'Electronic Signatures',
        body: 'Each signer receives a unique link. By drawing or uploading their signature at that link, they agree to electronically sign the document. Ravsign records the date, time, and link used for each signature.',
    },
    {
        title: 'Document Content',
        body: 'Your documents remain yours. You grant us only the permission needed to store them, show them to the signers you add, and generate the final signed copy.',
    },
    {
        title: 'Prohibited Uses',
        body: 'You may not use Ravsign to impersonate another person, forge signatures, distribute illegal content, or attempt to access other users’ documents.',
    },
    {
        title: 'Suspension and Termination',
        body: 'You may close your account at any time. We may suspend accounts that violate these terms, notifying you when possible.',
    },
    {
        title: 'Liability',
        body: 'Ravsign is provided as is. We are not a party to the agreements you sign and are not responsible for the content of your documents or their legal validity in any given jurisdiction.',
    },
    {
        title: 'Changes to These Terms',
        body: 'If we change these terms, we will notify you by email in advance. Continuing to use the service after the change means you accept it.',
    },
];

const PRIVACY_SECTIONS = [
    {
        title: 'Data We Collect',
        body: 'Your account name and email; the documents you upload; the names and initials of the signers you add; drawn or uploaded signatures; and technical data such as IP address, browser, and the date of each action.',
    },
    {
        title: 'How We Use It',
        body: 'To provide the service: show the document to each signer, record who signed and when, generate the final copy, and send you notices about the status of your documents.',
    },
    {
        title: 'Signatures',
        body: 'Each person’s signature image is used only in the fields assigned to them within the document they are signing. We do not reuse it in other documents unless they provide it again.',
    },
    {
        title: 'Who We Share It With',
        body: 'The signers of a document can see that document. We use hosting and email-delivery providers who process data on our behalf. We do not sell personal data.',
    },
    {
        title: 'Retention',
        body: 'We keep documents for as long as your account is active or until you delete them. The signature record is kept for as long as needed to prove the document’s validity.',
    },
    {
        title: 'Security',
        body: 'Documents are encrypted in transit and at rest. Each signing link is unique and grants access only to the corresponding document.',
    },
    {
        title: 'Your Rights',
        body: 'You can access, correct, export, or delete your data from your account or by writing to us. You can also object to specific processing or file a complaint with a data protection authority.',
    },
];

const isTerms = computed(() => props.type === 'terms');

const pageTitle = computed(() =>
    isTerms.value ? 'Terms of Service' : 'Privacy Policy',
);

const intro = computed(() =>
    isTerms.value
        ? 'These terms govern the use of Ravsign, a service for uploading documents, placing signature fields, and collecting electronic signatures from other people. By creating an account or signing a document, you accept these terms.'
        : 'This policy explains what data Ravsign collects when you upload or sign a document, what we use it for, and what control you have over it.',
);

const sections = computed(() => {
    const list = isTerms.value ? TERMS_SECTIONS : PRIVACY_SECTIONS;

    return list.map((section, index) => ({
        ...section,
        num: index + 1,
        anchor: `legal-${index + 1}`,
    }));
});

function jumpTo(anchor: string): void {
    const el = document.getElementById(anchor);

    if (el) {
        window.scrollTo({
            top: el.getBoundingClientRect().top + window.scrollY - 16,
            behavior: 'smooth',
        });
    }
}

function goBack(): void {
    if (window.history.length > 1) {
        window.history.back();

        return;
    }

    window.location.href = home().url;
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="min-h-dvh bg-muted">
        <header class="border-b border-border bg-white px-[clamp(16px,4vw,32px)]">
            <div
                class="mx-auto flex h-[68px] max-w-[1100px] items-center justify-between gap-3"
            >
                <Link :href="home()" class="flex items-center gap-2.5">
                    <AppLogo />
                </Link>
                <Button type="button" variant="outline" size="sm" @click="goBack">
                    ← Back
                </Button>
            </div>
        </header>

        <div
            class="mx-auto flex max-w-[1100px] flex-col gap-7 px-[clamp(16px,4vw,32px)] pt-[clamp(28px,5vw,56px)] pb-16"
        >
            <div class="flex flex-col gap-2.5">
                <span
                    class="text-[13px] font-semibold tracking-[0.06em] text-primary uppercase"
                    >Legal</span
                >
                <h1
                    class="text-[clamp(28px,5vw,40px)] font-semibold tracking-[-0.02em]"
                >
                    {{ pageTitle }}
                </h1>
                <span class="text-sm text-muted-foreground"
                    >Last updated: September 29, 2026</span
                >
            </div>

            <div
                class="flex max-w-full gap-1 self-start rounded-xl bg-[#e9edf2] p-1"
            >
                <Link
                    :href="terms()"
                    data-test="legal-tab-terms"
                    class="rounded-[9px] px-[18px] py-2.5 text-sm font-semibold"
                    :class="
                        isTerms
                            ? 'bg-white text-foreground shadow-sm'
                            : 'text-muted-foreground'
                    "
                >
                    Terms
                </Link>
                <Link
                    :href="privacy()"
                    data-test="legal-tab-privacy"
                    class="rounded-[9px] px-[18px] py-2.5 text-sm font-semibold"
                    :class="
                        !isTerms
                            ? 'bg-white text-foreground shadow-sm'
                            : 'text-muted-foreground'
                    "
                >
                    Privacy
                </Link>
            </div>

            <div class="flex items-start gap-7">
                <nav
                    class="sticky top-6 hidden w-60 shrink-0 flex-col gap-0.5 desk:flex"
                    aria-label="On this page"
                >
                    <span
                        class="px-3 pb-2 text-xs font-semibold tracking-[0.06em] text-muted-foreground uppercase"
                        >On this page</span
                    >
                    <button
                        v-for="s in sections"
                        :key="s.anchor"
                        type="button"
                        class="rounded-lg px-3 py-2 text-left text-sm text-foreground hover:bg-[#e9edf2]"
                        @click="jumpTo(s.anchor)"
                    >
                        {{ s.num }}. {{ s.title }}
                    </button>
                </nav>

                <article
                    class="flex min-w-0 flex-1 flex-col gap-8 rounded-2xl border border-border bg-white p-[clamp(22px,4vw,44px)]"
                >
                    <p class="text-base leading-[1.7] text-pretty text-foreground/90">
                        {{ intro }}
                    </p>

                    <section
                        v-for="s in sections"
                        :id="s.anchor"
                        :key="s.anchor"
                        class="flex scroll-mt-6 flex-col gap-2.5"
                    >
                        <h2 class="text-xl font-semibold">
                            {{ s.num }}. {{ s.title }}
                        </h2>
                        <p
                            class="text-[15px] leading-[1.7] text-pretty text-foreground/90"
                        >
                            {{ s.body }}
                        </p>
                    </section>

                    <div
                        class="border-t border-border pt-6 text-sm leading-[1.6] text-muted-foreground"
                    >
                        If you have questions about this document, write to us
                        at
                        <a href="mailto:legal@ravsign.site" class="font-semibold text-primary"
                            >legal@ravsign.site</a
                        >.
                    </div>
                </article>
            </div>
        </div>
    </div>
</template>
