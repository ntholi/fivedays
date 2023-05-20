import React, { createContext, useContext, useReducer } from 'react';

interface State {
  studentSubmissions: StudentSubmission[];
}

interface Action {
  type: string;
  payload?: any;
}

const initialState: State = {
  studentSubmissions: [],
};

const reducer = (state: State, action: Action): State => {
  switch (action.type) {
    case 'ADD_STUDENT_SUBMISSION':
      return {
        ...state,
        studentSubmissions: [...state.studentSubmissions, action.payload],
      };
    case 'REMOVE_STUDENT_SUBMISSION':
      return {
        ...state,
        studentSubmissions: state.studentSubmissions.filter(
          (submission) => submission.id !== action.payload
        ),
      };
    case 'ADD_RUBRIC_GRADE':
      return {
        ...state,
        studentSubmissions: state.studentSubmissions.map((submission) => {
          if (submission.id === action.payload.submissionId) {
            const rubricGrades = submission.rubricGrades
              ? [...submission.rubricGrades, action.payload.rubricGrade]
              : [action.payload.rubricGrade];
            const draftGrade = rubricGrades.reduce(
              (total, grade) => total + grade.points,
              0
            );
            return {
              ...submission,
              rubricGrades,
              draftGrade,
            };
          }
          return submission;
        }),
      };
    case 'REMOVE_RUBRIC_GRADE':
      return {
        ...state,
        studentSubmissions: state.studentSubmissions.map((submission) => {
          if (submission.id === action.payload.submissionId) {
            const rubricGrades = submission.rubricGrades?.filter(
              (grade) => grade.title !== action.payload.title
            );
            const draftGrade = rubricGrades?.reduce(
              (total, grade) => total + grade.points,
              0
            );
            return {
              ...submission,
              rubricGrades,
              draftGrade,
            };
          }
          return submission;
        }),
      };
    default:
      return state;
  }
};

const StudentSubmissionContext = createContext<{
  state: State;
  addSubmission: (submission: StudentSubmission) => void;
  removeSubmission: (id: string) => void;
  addRubricGrade: (submissionId: string, rubricGrade: RubricGrade) => void;
  removeRubricGrade: (submissionId: string, title: string) => void;
}>({
  state: initialState,
  addSubmission: () => null,
  removeSubmission: () => null,
  addRubricGrade: () => null,
  removeRubricGrade: () => null,
});

export const useStudentSubmissionContext = () =>
  useContext(StudentSubmissionContext);

export const StudentSubmissionProvider: React.FC = ({ children }) => {
  const [state, dispatch] = useReducer(reducer, initialState);

  const addSubmission = (submission: StudentSubmission) => {
    dispatch({
      type: 'ADD_STUDENT_SUBMISSION',
      payload: submission,
    });
  };

  const removeSubmission = (id: string) => {
    dispatch({
      type: 'REMOVE_STUDENT_SUBMISSION',
      payload: id,
    });
  };

  const addRubricGrade = (submissionId: string, rubricGrade: RubricGrade) => {
    dispatch({
      type: 'ADD_RUBRIC_GRADE',
      payload: {
        submissionId,
        rubricGrade,
      },
    });
  };

  const removeRubricGrade = (submissionId: string, title: string) => {
    dispatch({
      type: 'REMOVE_RUBRIC_GRADE',
      payload: {
        submissionId,
        title,
      },
    });
  };

  return (
    <StudentSubmissionContext.Provider
      value={{
        state,
        addSubmission,
        removeSubmission,
        addRubricGrade,
        removeRubricGrade,
      }}
    >
      {children}
    </StudentSubmissionContext.Provider>
  );
};
