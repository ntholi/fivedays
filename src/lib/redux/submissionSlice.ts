import { createSlice } from '@reduxjs/toolkit';
import type { PayloadAction } from '@reduxjs/toolkit';
import { classroom_v1 } from 'googleapis';

export interface SubmissionState {
  value: {
    student: classroom_v1.Schema$Student | undefined | null;
    submission: classroom_v1.Schema$StudentSubmission | undefined | null;
  };
}

const initialState: SubmissionState = {
  value: {
    student: null,
    submission: null,
  },
};

export const studentSubmissionSlice = createSlice({
  name: 'studentSubmission',
  initialState,
  reducers: {
    setStudentSubmission: (
      state,
      action: PayloadAction<{
        student: classroom_v1.Schema$Student | null;
        submission: classroom_v1.Schema$StudentSubmission | undefined | null;
      }>
    ) => {
      state.value.student = action.payload.student;
      state.value.submission = action.payload.submission;
    },
  },
});

export const { setStudentSubmission } = studentSubmissionSlice.actions;

export default studentSubmissionSlice.reducer;
