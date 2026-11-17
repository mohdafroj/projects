import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { Employee, EmployeeInput, EmployeePage, EmployeeSummary } from './models';

export interface EmployeeQuery {
  search: string;
  status: string;
  department: string;
  sortBy: string;
  sortOrder: 'asc' | 'desc';
  page: number;
  limit: number;
}

@Injectable({ providedIn: 'root' })
export class EmployeeService {
  private readonly endpoint = '/api/employees';

  constructor(private readonly http: HttpClient) {}

  list(query: EmployeeQuery): Observable<EmployeePage> {
    let params = new HttpParams();
    Object.entries(query).forEach(([key, value]) => {
      params = params.set(key, String(value));
    });
    return this.http.get<EmployeePage>(this.endpoint, { params });
  }

  summary(): Observable<EmployeeSummary> {
    return this.http.get<EmployeeSummary>(`${this.endpoint}/summary`);
  }

  create(employee: EmployeeInput): Observable<{ employee: Employee }> {
    return this.http.post<{ employee: Employee }>(this.endpoint, employee);
  }

  update(id: string, employee: EmployeeInput): Observable<{ employee: Employee }> {
    return this.http.put<{ employee: Employee }>(`${this.endpoint}/${id}`, employee);
  }

  delete(id: string): Observable<void> {
    return this.http.delete<void>(`${this.endpoint}/${id}`);
  }
}
