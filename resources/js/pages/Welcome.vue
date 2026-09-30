<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { home, login, register } from '@/routes';
import { index as documents } from '@/routes/documents';

const steps = [
    {
        number: '01',
        title: 'Sube tu documento',
        text: 'Carga un PDF desde tu equipo. Lo verás en vista previa antes de prepararlo.',
    },
    {
        number: '02',
        title: 'Asigna los campos',
        text: 'Agrega firmantes y arrastra campos de firma, iniciales, fecha o nombre sobre cada página.',
    },
    {
        number: '03',
        title: 'Recibe las firmas',
        text: 'Cada firmante dibuja su firma o sube una imagen. El documento se marca como completado al terminar.',
    },
];

const features = [
    {
        title: 'Firma dibujada o con imagen',
        text: 'Traza la firma con el mouse o el dedo, o sube una foto de tu firma.',
    },
    {
        title: 'Varios firmantes',
        text: 'Cada firmante tiene su color, así sabes qué campo le corresponde a quién.',
    },
    {
        title: 'Estado de cada documento',
        text: 'Borradores, pendientes y completados, organizados en un solo panel.',
    },
];

const documentLines = ['92%', '100%', '84%', '96%'];
</script>

<template>
    <Head title="Firma electrónica de documentos" />

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
                <a href="#como" class="text-foreground">Cómo funciona</a>
                <a href="#funciones" class="text-foreground">Funciones</a>
            </nav>

            <div class="flex items-center gap-3">
                <template v-if="$page.props.auth.user">
                    <Button as-child>
                        <Link :href="documents()">Ir a Documentos</Link>
                    </Button>
                </template>
                <template v-else>
                    <Link
                        :href="login()"
                        class="px-2 py-2.5 text-[15px] font-semibold whitespace-nowrap text-foreground"
                    >
                        Iniciar sesión
                    </Link>
                    <Button as-child class="hidden desk:inline-flex">
                        <Link :href="register()">Crear cuenta gratis</Link>
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
                    Firma electrónica de documentos
                </span>
                <h1
                    class="text-[clamp(36px,9vw,56px)] leading-[1.08] font-semibold tracking-[-0.02em] text-balance"
                >
                    Firma y envía documentos en minutos.
                </h1>
                <p
                    class="max-w-[520px] text-[clamp(16px,2.4vw,18px)] leading-[1.6] text-pretty text-muted-foreground"
                >
                    Sube un PDF, arrastra los campos de firma donde los
                    necesites y envíalo. Tus firmantes firman desde cualquier
                    dispositivo, dibujando su firma o subiendo una imagen.
                </p>
                <div class="mt-2 flex flex-wrap gap-3">
                    <Button as-child size="lg" class="flex-auto rounded-xl">
                        <Link :href="register()">Empieza gratis</Link>
                    </Button>
                    <Button
                        as-child
                        variant="outline"
                        size="lg"
                        class="flex-auto rounded-xl border-[1.5px] border-input bg-white text-foreground hover:border-foreground hover:bg-white hover:text-foreground"
                    >
                        <Link :href="login()">Probar el editor</Link>
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
                        Acuerdo de servicios
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
                            Firmar aquí
                        </div>
                        <div
                            class="flex h-[58px] w-24 items-center justify-center rounded-md border-[1.5px] border-dashed border-[#7a4fc9] bg-[#7a4fc9]/10 text-[13px] font-semibold text-[#7a4fc9]"
                        >
                            Fecha
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
                            >Carlos Ruiz firmó</span
                        >
                        <span class="text-xs text-muted-foreground"
                            >Hace 2 minutos</span
                        >
                    </div>
                </div>

                <div
                    class="absolute top-[clamp(16px,3vw,32px)] left-[clamp(12px,3vw,24px)] rounded-full bg-foreground px-3.5 py-2 text-xs font-semibold text-white"
                >
                    2 de 3 firmas
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
                    Cómo funciona
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
                    Tu primer documento firmado hoy.
                </h2>
                <Button as-child size="lg" class="rounded-xl">
                    <Link :href="register()">Crear cuenta gratis</Link>
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
                <span>© 2026 Ravsign · Secure Document Signing</span>
            </div>
        </footer>
    </div>
</template>
