import { Points } from '@prisma/client';
import { createSlice } from '@reduxjs/toolkit';
import type { PayloadAction } from '@reduxjs/toolkit';
import { classroom_v1 } from 'googleapis';

export interface SubmissionState {
  value: {
    student: classroom_v1.Schema$Student | undefined | null;
    submission: classroom_v1.Schema$StudentSubmission | undefined | null;
    pointsList: {
      rubricId: string;
      points: number;
    }[];
  };
}

const initialState: SubmissionState = {
  value: {
    student: null,
    submission: null,
    pointsList: [],
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
    setPoints: (
      state,
      action: PayloadAction<{
        rubricId: string;
        points: number;
      }>
    ) => {
      const { rubricId, points } = action.payload;
      const index = state.value.pointsList.findIndex(
        (points) => points.rubricId === rubricId
      );
      if (index === -1) {
        state.value.pointsList.push({ rubricId, points });
      } else {
        state.value.pointsList[index].points = points;
      }
    },
  },
});

export const { setStudentSubmission } = studentSubmissionSlice.actions;

export default studentSubmissionSlice.reducer;
