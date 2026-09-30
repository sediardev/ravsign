<script setup lang="ts">
import { X } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SIGNER_COLORS } from '@/lib/documents';
import type { Signer } from '@/types';

export type SignerForm = { name: string; email: string; siglas: string };

defineProps<{
    signers: Signer[];
    activeSignerId: string | null;
    editable: boolean;
    canAdd: boolean;
    errors: Record<string, string>;
    adding: boolean;
}>();

const emit = defineEmits<{
    select: [id: string];
    remove: [signer: Signer];
    add: [];
}>();

const form = defineModel<SignerForm>('form', { required: true });

const siglasPlaceholder = computed(() => {
    const initials = form.value.name
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0].toUpperCase())
        .join('');

    return initials || 'Siglas';
});
</script>

<template>
    <div data-test="signers-list">
        <ul class="flex flex-col gap-2">
            <li
                v-for="signer in signers"
                :key="signer.id"
                class="flex items-center gap-1 rounded-[10px] border-[1.5px] pr-1 transition-colors"
                :class="
                    activeSignerId === signer.id
                        ? 'bg-[#eaf4f8]'
                        : 'border-border bg-white'
                "
                :style="
                    activeSignerId === signer.id
                        ? { borderColor: signer.color }
                        : undefined
                "
                data-test="signer-row"
            >
                <button
                    type="button"
                    class="flex min-w-0 flex-1 items-center gap-2.5 p-2.5 text-left"
                    @click="emit('select', signer.id)"
                >
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-full text-[11px] font-bold text-white"
                        :style="{ backgroundColor: signer.color }"
                        >{{ signer.siglas }}</span
                    >
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold">{{
                            signer.name
                        }}</span>
                        <span
                            class="block truncate text-xs text-muted-foreground"
                            >{{ signer.email }}</span
                        >
                    </span>
                </button>
                <button
                    v-if="editable"
                    type="button"
                    class="flex size-7 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-[#eef0f3] hover:text-foreground"
                    :aria-label="`Quitar a ${signer.name}`"
                    data-test="remove-signer"
                    @click="emit('remove', signer)"
                >
                    <X class="size-4" />
                </button>
            </li>
        </ul>

        <p
            v-if="signers.length === 0"
            class="py-2 text-sm text-muted-foreground"
        >
            Todavía no hay firmantes.
        </p>

        <form
            v-if="canAdd"
            class="mt-4 flex flex-col gap-3 border-t border-border pt-4"
            data-test="add-signer-form"
            @submit.prevent="emit('add')"
        >
            <h3 class="text-sm font-semibold">Agregar firmante</h3>

            <div class="flex flex-col gap-1.5">
                <Label for="signer-name">Nombre</Label>
                <Input
                    id="signer-name"
                    v-model="form.name"
                    class="h-10"
                    autocomplete="off"
                    placeholder="Nombre completo"
                    :aria-invalid="!!errors.name"
                />
                <p v-if="errors.name" class="text-xs text-destructive">
                    {{ errors.name }}
                </p>
            </div>

            <div class="flex flex-col gap-1.5">
                <Label for="signer-email">Correo</Label>
                <Input
                    id="signer-email"
                    v-model="form.email"
                    type="email"
                    class="h-10"
                    autocomplete="off"
                    placeholder="correo@ejemplo.com"
                    :aria-invalid="!!errors.email"
                />
                <p v-if="errors.email" class="text-xs text-destructive">
                    {{ errors.email }}
                </p>
            </div>

            <div class="flex flex-col gap-1.5">
                <Label for="signer-siglas">Siglas</Label>
                <Input
                    id="signer-siglas"
                    v-model="form.siglas"
                    class="h-10 uppercase"
                    maxlength="4"
                    autocomplete="off"
                    :placeholder="siglasPlaceholder"
                    :aria-invalid="!!errors.siglas"
                />
                <p v-if="errors.siglas" class="text-xs text-destructive">
                    {{ errors.siglas }}
                </p>
            </div>

            <Button type="submit" size="sm" :disabled="adding">
                {{ adding ? 'Agregando…' : 'Agregar' }}
            </Button>
        </form>

        <p
            v-else-if="editable"
            class="mt-4 border-t border-border pt-4 text-xs text-muted-foreground"
        >
            Un documento admite hasta {{ SIGNER_COLORS.length }} firmantes.
        </p>

        <p
            v-else
            class="mt-4 border-t border-border pt-4 text-xs text-muted-foreground"
        >
            Este documento ya se envió a firma y no se puede modificar.
        </p>
    </div>
</template>
