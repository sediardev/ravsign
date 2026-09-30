<script setup lang="ts">
import { Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { SignField, Signer } from '@/types';

defineProps<{
    field: SignField;
    signers: Signer[];
    /** One-row layout for the mobile bottom bar. */
    compact?: boolean;
}>();

const emit = defineEmits<{
    reassign: [signerId: unknown];
    remove: [];
}>();
</script>

<template>
    <section
        v-if="compact"
        class="flex items-center gap-2"
        data-test="selected-field-panel"
    >
        <Select
            :model-value="field.signerId"
            @update:model-value="emit('reassign', $event)"
        >
            <SelectTrigger
                class="h-10 min-w-0 flex-1 bg-white"
                aria-label="Asignado a"
                data-test="field-signer-select"
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="signer in signers"
                    :key="signer.id"
                    :value="signer.id"
                >
                    {{ signer.name }}
                </SelectItem>
            </SelectContent>
        </Select>
        <Button
            type="button"
            variant="outline"
            size="icon"
            class="size-10 shrink-0 text-destructive hover:text-destructive"
            aria-label="Eliminar campo"
            data-test="delete-field"
            @click="emit('remove')"
        >
            <Trash2 class="size-4" />
        </Button>
    </section>

    <section
        v-else
        class="mb-5 flex flex-col gap-3 rounded-[10px] border border-border bg-[#f6f8fb] p-3"
        data-test="selected-field-panel"
    >
        <h2 class="text-sm font-semibold">
            Campo seleccionado
            <span class="font-normal text-muted-foreground"
                >· Página {{ field.page + 1 }}</span
            >
        </h2>

        <div class="flex flex-col gap-1.5">
            <Label for="field-signer">Asignado a</Label>
            <Select
                :model-value="field.signerId"
                @update:model-value="emit('reassign', $event)"
            >
                <SelectTrigger
                    id="field-signer"
                    class="h-10 w-full bg-white"
                    data-test="field-signer-select"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="signer in signers"
                        :key="signer.id"
                        :value="signer.id"
                    >
                        {{ signer.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <Button
            type="button"
            variant="outline"
            size="sm"
            class="text-destructive hover:text-destructive"
            data-test="delete-field"
            @click="emit('remove')"
        >
            <Trash2 class="size-4" />
            Eliminar campo
        </Button>
        <p class="text-xs text-muted-foreground">
            También puedes pulsar Suprimir.
        </p>
    </section>
</template>
