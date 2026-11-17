import { CommonModule } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Subject, debounceTime, distinctUntilChanged, finalize } from 'rxjs';
import { AuthService } from './auth.service';
import { EmployeeService } from './employee.service';
import { Employee, EmployeeInput, EmployeeStatus, EmployeeSummary } from './models';

const emptySummary: EmployeeSummary = {
  total: 0,
  active: 0,
  inactive: 0,
  departments: 0,
  departmentNames: [],
  months: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
  hiresByMonth: Array(12).fill(0) as number[],
  exitsByMonth: Array(12).fill(0) as number[]
};

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './app.component.html',
  styleUrl: './app.component.scss'
})
export class AppComponent implements OnInit {
  readonly Math = Math;
  readonly auth = inject(AuthService);
  private readonly employeesApi = inject(EmployeeService);
  private readonly fb = inject(FormBuilder);
  readonly employees = signal<Employee[]>([]);
  readonly summary = signal<EmployeeSummary>(emptySummary);
  readonly total = signal(0);
  readonly page = signal(1);
  readonly pages = signal(1);
  readonly search = signal('');
  readonly statusFilter = signal('');
  readonly departmentFilter = signal('');
  readonly sortBy = signal('joinedAt');
  readonly sortOrder = signal<'asc' | 'desc'>('desc');
  readonly loading = signal(false);
  readonly saving = signal(false);
  readonly error = signal('');
  readonly modalError = signal('');
  readonly editorOpen = signal(false);
  readonly editing = signal<Employee | null>(null);
  readonly sidebarOpen = signal(false);
  readonly pageSize = 8;
  readonly pageNumbers = computed(() => Array.from({ length: this.pages() }, (_, index) => index + 1));
  readonly canGoPrevious = computed(() => this.page() > 1);
  readonly canGoNext = computed(() => this.page() < this.pages());
  readonly monthMaximum = computed(() => Math.max(
    10,
    ...this.summary().hiresByMonth,
    ...this.summary().exitsByMonth
  ));
  readonly searchChanges = new Subject<string>();
  readonly statuses: Array<{ value: EmployeeStatus; label: string }> = [
    { value: 'active', label: 'Active' },
    { value: 'on-leave', label: 'On Leave' },
    { value: 'pending', label: 'Pending' },
    { value: 'inactive', label: 'Inactive' }
  ];
  readonly employeeForm = this.fb.group({
    employeeId: ['', [Validators.required, Validators.maxLength(20)]],
    name: ['', [Validators.required, Validators.maxLength(100)]],
    email: ['', [Validators.required, Validators.email, Validators.maxLength(254)]],
    department: ['', [Validators.required, Validators.maxLength(80)]],
    jobTitle: ['', [Validators.required, Validators.maxLength(100)]],
    joinedAt: ['', Validators.required],
    status: ['active' as EmployeeStatus, Validators.required]
  });
  readonly loginForm = this.fb.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', Validators.required]
  });
  readonly departments = computed(() => this.summary().departmentNames);
  readonly updatedAt = new Date();
  readonly notice = signal('');

  ngOnInit(): void {
    this.searchChanges.pipe(debounceTime(250), distinctUntilChanged()).subscribe((value) => {
      this.search.set(value.trim());
      this.page.set(1);
      this.loadEmployees();
    });
    if (this.auth.isAuthenticated()) this.loadDashboard();
  }

  loadDashboard(): void {
    this.error.set('');
    this.loadEmployees();
    this.employeesApi.summary().subscribe({
      next: (summary) => this.summary.set(summary),
      error: (error: HttpErrorResponse) => this.handleApiError(error)
    });
  }

  loadEmployees(): void {
    this.loading.set(true);
    this.employeesApi.list({
      search: this.search(),
      status: this.statusFilter(),
      department: this.departmentFilter(),
      sortBy: this.sortBy(),
      sortOrder: this.sortOrder(),
      page: this.page(),
      limit: this.pageSize
    }).pipe(finalize(() => this.loading.set(false))).subscribe({
      next: (result) => {
        this.employees.set(result.items);
        this.total.set(result.total);
        this.pages.set(Math.max(1, result.pages));
      },
      error: (error: HttpErrorResponse) => this.handleApiError(error)
    });
  }

  login(): void {
    if (this.loginForm.invalid || this.saving()) {
      this.loginForm.markAllAsTouched();
      return;
    }
    this.saving.set(true);
    this.error.set('');
    const { email, password } = this.loginForm.getRawValue();
    this.auth.login(email!, password!).pipe(finalize(() => this.saving.set(false))).subscribe({
      next: () => this.loadDashboard(),
      error: (error: HttpErrorResponse) => this.error.set(this.errorMessage(error))
    });
  }

  logout(): void {
    this.auth.logout().subscribe({
      next: () => this.resetDashboard(),
      error: () => {
        this.auth.clearSession();
        this.resetDashboard();
      }
    });
  }

  openCreate(): void {
    this.editing.set(null);
    this.modalError.set('');
    this.employeeForm.reset({ employeeId: '', name: '', email: '', department: '', jobTitle: '', joinedAt: '', status: 'active' });
    this.editorOpen.set(true);
  }

  openEdit(employee: Employee): void {
    this.editing.set(employee);
    this.modalError.set('');
    this.employeeForm.reset({
      employeeId: employee.employeeId,
      name: employee.name,
      email: employee.email,
      department: employee.department,
      jobTitle: employee.jobTitle,
      joinedAt: employee.joinedAt.slice(0, 10),
      status: employee.status
    });
    this.editorOpen.set(true);
  }

  closeEditor(): void {
    if (!this.saving()) this.editorOpen.set(false);
  }

  saveEmployee(): void {
    if (this.employeeForm.invalid || this.saving()) {
      this.employeeForm.markAllAsTouched();
      return;
    }
    const input = this.employeeForm.getRawValue() as EmployeeInput;
    const employee = this.editing();
    this.saving.set(true);
    this.modalError.set('');
    const request = employee
      ? this.employeesApi.update(employee._id, input)
      : this.employeesApi.create(input);
    request.pipe(finalize(() => this.saving.set(false))).subscribe({
      next: () => {
        this.editorOpen.set(false);
        this.notice.set(employee ? 'Employee updated' : 'Employee added');
        this.loadDashboard();
        setTimeout(() => this.notice.set(''), 3000);
      },
      error: (error: HttpErrorResponse) => this.modalError.set(this.errorMessage(error))
    });
  }

  deleteEmployee(employee: Employee): void {
    if (!window.confirm(`Remove ${employee.name} from the employee directory?`)) return;
    this.employeesApi.delete(employee._id).subscribe({
      next: () => {
        if (this.employees().length === 1 && this.page() > 1) this.page.update((page) => page - 1);
        this.notice.set('Employee removed');
        this.loadDashboard();
        setTimeout(() => this.notice.set(''), 3000);
      },
      error: (error: HttpErrorResponse) => this.error.set(this.errorMessage(error))
    });
  }

  changeStatus(status: string): void {
    this.statusFilter.set(status);
    this.page.set(1);
    this.loadEmployees();
  }

  changeDepartment(department: string): void {
    this.departmentFilter.set(department);
    this.page.set(1);
    this.loadEmployees();
  }

  toggleSort(field: string): void {
    if (this.sortBy() === field) this.sortOrder.set(this.sortOrder() === 'asc' ? 'desc' : 'asc');
    else {
      this.sortBy.set(field);
      this.sortOrder.set('asc');
    }
    this.loadEmployees();
  }

  goToPage(page: number): void {
    if (page < 1 || page > this.pages() || page === this.page()) return;
    this.page.set(page);
    this.loadEmployees();
  }

  exportCsv(): void {
    const headers = ['Employee ID', 'Name', 'Email', 'Department', 'Job Title', 'Joined Date', 'Status'];
    const rows = this.employees().map((employee) => [
      employee.employeeId,
      employee.name,
      employee.email,
      employee.department,
      employee.jobTitle,
      this.formatDate(employee.joinedAt),
      this.statusLabel(employee.status)
    ]);
    const escape = (value: string) => `"${value.replace(/"/g, '""')}"`;
    const csv = [headers, ...rows].map((row) => row.map(escape).join(',')).join('\r\n');
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = 'employees.csv';
    link.click();
    URL.revokeObjectURL(url);
  }

  formatDate(date: string): string {
    return new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(date));
  }

  statusLabel(status: EmployeeStatus): string {
    return this.statuses.find((item) => item.value === status)?.label || status;
  }

  barHeight(value: number): number {
    return Math.max(value > 0 ? 6 : 0, Math.round((value / this.monthMaximum()) * 100));
  }

  avatarColor(name: string): string {
    const colors = ['mint', 'lavender', 'peach', 'blue', 'pink'];
    return colors[name.charCodeAt(0) % colors.length];
  }

  trackEmployee(_: number, employee: Employee): string {
    return employee._id;
  }

  private handleApiError(error: HttpErrorResponse): void {
    if (error.status === 401) {
      this.auth.clearSession();
      this.resetDashboard();
      this.error.set('Your session expired. Please sign in again.');
      return;
    }
    this.error.set(this.errorMessage(error));
  }

  private errorMessage(error: HttpErrorResponse): string {
    if (error.error && typeof error.error.message === 'string') return error.error.message;
    if (error.status === 0) return 'Cannot reach the server. Check that the API is running and try again.';
    return 'Something went wrong. Please try again.';
  }

  private resetDashboard(): void {
    this.employees.set([]);
    this.summary.set(emptySummary);
    this.total.set(0);
    this.page.set(1);
    this.pages.set(1);
    this.search.set('');
    this.statusFilter.set('');
    this.departmentFilter.set('');
  }
}
