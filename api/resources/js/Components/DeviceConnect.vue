<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { displayTime } from './TrackingTypes';
const props = defineProps<{ connection: { host: string; port: number }; discovery: { imei: string; last_seen_at: string } | null }>();
const emit = defineEmits<{ close: [] }>();
const form = useForm({ imei: '', proof: '', name: '' });
const phone = ref('');
const copied = ref('');
const command = computed(() => `SERVER,${/^\d+\.\d+\.\d+\.\d+$/.test(props.connection.host) ? 0 : 1},${props.connection.host},${props.connection.port}#`);
const verified = computed(() => props.discovery?.imei === form.imei && !form.hasErrors);
async function copy() { try { await navigator.clipboard.writeText(command.value); copied.value = 'Command copied.'; } catch { copied.value = 'Select the command and copy it manually.'; } }
</script>
<template>
    <section class="panel" aria-labelledby="connect-title">
        <div class="panel-head"><div><h2 id="connect-title">Connect a device</h2><p class="muted">Send the setup command, then verify ownership.</p></div><button class="btn" @click="emit('close')">Close</button></div>
        <div class="setup-grid panel-body">
            <div><div class="section-label">01 · Configure the tracker</div><h3>Send an SMS from your phone</h3><p class="muted">Send this command to the SIM inside your tracker. SMS charges may apply through your carrier.</p><label class="field">Tracker SIM phone number<input v-model="phone" type="tel" autocomplete="tel" placeholder="Include country code" /></label><code class="command">{{ command }}</code><div class="actions"><button class="btn" @click="copy">Copy command</button><a v-if="phone" class="btn" :href="`sms:${phone.replace(/[^+\d]/g, '')}?body=${encodeURIComponent(command)}`">Open SMS app</a></div><p role="status" class="muted">{{ copied }}</p><p class="field-hint">Keep the tracker powered with mobile data available. A successful SMS reply does not yet confirm a server connection.</p></div>
            <div><div class="section-label">02 · Verify and claim</div><h3>Find your device securely</h3><form @submit.prevent="form.post('/customer/devices/discover', { preserveScroll: true })"><label class="field">IMEI<input v-model="form.imei" required inputmode="numeric" pattern="[0-9]{15}" maxlength="15" aria-describedby="imei-error" /><small>Enter the 15 digits printed on the device.</small><span id="imei-error" class="field-error">{{ form.errors.imei }}</span></label><label class="field">SIM ICCID or private claim code<input v-model="form.proof" required autocomplete="off" aria-describedby="proof-error" /><small>The full ICCID is printed on your SIM or its packaging. Contact support if it is unavailable.</small><span id="proof-error" class="field-error">{{ form.errors.proof }}</span></label><button class="btn" :disabled="form.processing">{{ form.processing ? 'Checking…' : 'Check connection' }}</button></form><form v-if="verified" class="claim-result" @submit.prevent="form.post('/customer/devices/claim', { onSuccess: () => emit('close') })"><p class="status online">Device verified · last connected {{ displayTime(discovery!.last_seen_at) }}</p><label class="field">Device name<input v-model="form.name" maxlength="120" required placeholder="For example, Family car" /><span class="field-error">{{ form.errors.name }}</span></label><button class="btn-primary" :disabled="form.processing">{{ form.processing ? 'Claiming…' : 'Claim device' }}</button></form></div>
        </div>
    </section>
</template>
