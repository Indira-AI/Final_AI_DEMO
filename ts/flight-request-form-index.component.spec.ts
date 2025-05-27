import { ComponentFixture, TestBed } from '@angular/core/testing';

import { FlightRequestFormIndexComponent } from './flight-request-form-index.component';

describe('FlightRequestFormIndexComponent', () => {
  let component: FlightRequestFormIndexComponent;
  let fixture: ComponentFixture<FlightRequestFormIndexComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [ FlightRequestFormIndexComponent ]
    })
    .compileComponents();
  });

  beforeEach(() => {
    fixture = TestBed.createComponent(FlightRequestFormIndexComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
