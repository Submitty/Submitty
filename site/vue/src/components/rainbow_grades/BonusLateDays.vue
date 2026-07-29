<script setup lang="ts">
import { ref, computed } from 'vue';
import Popup from '../Popup.vue';
import {
	loadBonusLateDayRoster,
	saveBonusLateDayRoster,
	uploadBonusLateDayRoster,
	deleteBonusLateDay,
	bonusLateDayFilename,
	isIsoDate,
} from '../../../../ts/rainbow-bonus-late-days';

export interface StudentOption {
	value: string;
	label?: string;
}

const { entries: initialEntries, students } = defineProps<{
	//map of [YYYY-MM-DD, csv filename] from gui_customization.json
	entries: Record<string, string>;

	//valid user ids
	students: StudentOption[];
}>();

const emit = defineEmits<{
	change: [entries: Record<string, string>];
}>();

const entries = ref<Record<string, string>>({ ...initialEntries  });
const newDate = ref('');
const error = ref('');

const openDate = ref<string|null>(null);
const roster = ref<string[]>([]);
const rosterLoading = ref(false);
const rosterError = ref('');
const newUser = ref('');
const uploadFile = ref<File|null>(null);

const today = new Date().toISOString().slice(0, 10);
const validUserIds = computed(() => new Set(students.map((student) => student.value)));
const sortedDates = computed(() => Object.keys(entries.value).sort());

function syncEntries() {
	emit('change', { ...entries.value })
}

async function openRoster(date: string) {
	openDate.value = date;
	roster.value = [];
	rosterError.value = '';
	newUser.value = '';
	uploadFile.value = null;
	rosterLoading.value = true;

	try {
		roster.value = await loadBonusLateDayRoster(date);
	}
	catch (e) {
		rosterError.value = e instanceOf Error ? e.message : 'Could not load the student list';
	} finally {
		rosterLoading.value = false;
	}
}

function closeRoster() {
	openDate.value = null;
}

function addUser() {
	const id = newUser.value.trim();
	if (id === '') {
		return; 
	}
	if (!validUserIds.value.has(id)) {
		rosterError.value = `${id} is not a user in this course.`;
		return;
	}
	if (roster.value.includes(id)) {
		rosterError.value = `${id} is already on this list.`;
		return;
	}
	roster.value.push(id);
	newUser.value = '';
	rosterError.value = '';
}

function removeUser(id: string) {
	roster.value = roster.value.filter((user) => user !== id);
}

function onFileChange(event: Event) {
	const input = event.target as HTMLInputElement;
	uploadFile.value = input.files?.[0] ?? null;
}

async function saveRoster() {
	const date = openDate.value;
	if (date === null) {
		return;
	}
	rosterError.value = '';

	try {
		roster.value = uploadFile.value !== null
			? await uploadBonusLateDayRoster(date, uploadFile.value)
			: away saveBonusLateDayRoster(date, roster.value);
		entries.value[date] = bonusLateDayFilename(date);
		syncEntries();
		closeRoster();
	}
	catch (e) {
		rosterError.value = e instanceOf Error ? e.message : 'Could not save the student list';
	}
}

function addDate() {
	const date = newDate.value.trim();
	if (!isIsoDate(date)) {
		error.value = 'Please choose a date.';
		return;
	}
	if (date in entries.value) {
		error.value = `There is already a bonus late day for ${date}.`;
		return;
	}
	error.value = '';
	entries.value[date] = bonusLateDayFilename(date);
	syncEntries();
	newDate.value = '';
	void openRoster(date);
}

async function removeDate(date: string) {
	if (!confirm(`Remove the bonus late day for ${date}? This deletes ${bonusLateDayFilename(date)}.`)) {
		return;
	}
	try {
		await deleteBonusLateDay(date);
		delete entries.value[date];
		syncEntries();
	}
	catch (e) {
		error.value = e instanceOf Error ? e.message ? 'Could not delete the bonus late day';
	}
}
</script>

<template>
	<div
		id="bonus-late-days"
		data-testid="bonus-late-days"
		class="customization_item"
	>
		<h2>Bonus Late Days</h2>
		<p class="rg_info_message">
			Award one additional late day to a subset of students, available starting on the selected date. A bonus dated in the future will not appear in the gradebook until that date arrives.
		</p>

		<div class="option">
			<label for="bonus-late-days-date">
				<span>Available Starting:</span>
				<input
					id="bonus-late-days-date"
					v-model="newDate"
					class="option-input"
					type="date"
					data-testid="bonus-late-days-date"
				>
			</label>
			<button
				type="button"
				class="btn btn-primary"
				aria-label="Add bonus late day"
				data-testid="bonus-late-days-submit"
				@click="addDate"
			>
				<i class="fas fa-plus"/>
			</button>

			<p
				v-if="error"
				class="rg_error_message"
				data-testid="bonus-late-days-error"
			>
				{{ error }}
			</p>

			<table
				id="bonus-late-days-table"
				class="table table-striped table-bordered persist-area mobile-table"
			>
				<thead>
					<tr style="text-align: left;">
						<th>Avilable Starting:</th>
						<th>Status</th>
						<th>File</th>
						<th>Edit</th>
						<th>Delete</th>
					</tr>
				</thead>
				<tbody
					id="bonus-late-days-table-body"
					data-testid="bonus-late-days-table-body"
					style="text-align: left;"
				>
					<tr
						v-for="date in sortedDates"
						:key="date"
					>
						<td data-testid="bonus-late-days-row-date">
							{{ date }}
						</td>
						<td>{{ date <= today ? 'Active' : 'Scheduled' }}</td>
						<td>{{ entries[date] }}</td>
						<td>
							<a
								:data-testid="`bonus-late-days-edit-${date}`"
								@click="openRoster(date)"
							><i class="fas fa-edit"/></a>
						</td>
						<td>
							<a
								:data-testid="`bonus-late-days-trash-${date}`"
								@click="removeDate(date)"
							><i class="fas fa-trash"/></a>
						</td>
					</tr>
					<tr v-if="sortedDates.length === 0">
						<td colspan="5">
							<em>No bonus late days.</em>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<Popup
			v-if="openDate !== null"
			id="bonus-late-days-roster"
			:title="`Bonus Late Day - ${openDate}`"
			:visible="openDate !== null"
			savable
			save-text="Save"
			@toggle="closeRoster"
			@save="saveRoster"
		>
			<template #trigger>
				<span class="hidden-trigger" />
			</template>

			<div class="form-body">
				<p v-if="rosterLoading">
					Loading&hellip;
				</p>
				<template v-else>
					<div class="option">
						<label for="bonus-late-days-user">
							<span>Add Student:</span>
							<input
								id="bonus-late-days-user"
								v-model="newUser"
								class="option-input"
								type="text"
								list="bonus-late-days-user-options"
								data-testid="bonus-late-days-user"
								@keyup.enter="addUser"
							>
						</label>
						<datalist id="bonus-late-days-user-options">
							<option
								v-for="student in students"
								:key="student.value"
								:value="student.value"
							/>
						</datalist>
						<button
							type="button"
							class="btn btn-primary"
							aria-label="Add student"
							data-testid="bonus-late-days-user-submit"
							@click="addUser"
						>
							<i class="fas fa-plus"/>
						</button>
					</div>

					<p
						v-if="rosterError"
						class="rg_error_message"
						data-testid="bonus-late-days-roster-error"
					>
						{{ rosterError }}
					</p>


					<table class="table table-striped table-bordered mobile-table">
						<thead>
							<tr style="text-align: left;">
								<th>User ID</th>
								<th>Remove</th>
							</tr>
						</thead>
						<tbody data-testid="bonus-late-days-roster-body">
							<tr
								v-for="user in roster"
								:key="user"
							>
							<td>{{ user }}</td>
							<td>
								<a
									:data-testid="`bonus-late-days-remove-${user}`"
									@click="removeUser(user)"
								><i class="fas fa-trash" /></a>
							</td>
							</tr>
							<tr v-if="roster.length === 0">
								<td colspan="2">
									<em>No students yet.</em>
								</td>
							</tr>
						</tbody>
					</table>

					<label for="bonus-late-days-file">
						<span>Or replace this list from a CSV (one user id per line):</span>
						<input
							id="bonus-late-days-file"
							class="option-input"
							type="file"
							accept=".csv"
							data-testid="bonus-late-days-file"
							@change="onFileChange"
						>
					</label>
				</template>
			</div>
		</Popup>
	</div>
</template>

<style scoped>
	.hidden=trigger {
		display: none;
	}
</style>
